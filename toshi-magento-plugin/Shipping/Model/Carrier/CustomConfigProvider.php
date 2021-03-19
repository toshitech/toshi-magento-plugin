<?php

namespace Toshi\Shipping\Model\Carrier;

use Magento\Checkout\Model\ConfigProviderInterface;
use Toshi\Shipping\Helper\Data;
use Magento\Checkout\Model\Session;
use Magento\Catalog\Model\Product\Attribute\Repository;
use Toshi\Shipping\Logger\Logger;
use \DateTime;

use Toshi\Shipping\Model\Adminhtml\Source\Mode;
use Toshi\Shipping\Model\Adminhtml\Source\Environment;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Api\ProductRepositoryInterface;

class CustomConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Session
     **/
    protected $session;

    /**
     * @var Repository
     */
    protected $productAttributeRepository;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param Session $session
     * @param Data $helper
     * @param Repository $productAttributeRepository,
     * @param Logger $logger
     */
    public function __construct(
        Session $session,
        Data $helper,
        Repository $productAttributeRepository,
        Logger $logger
    ) {
        $this->session = $session;
        $this->helper = $helper;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->logger = $logger;
    }

    /**
     * @return mixed[] Configuration
     */
    public function getConfig()
    {

        $script = $this->helper::SCRIPT_SANDBOX;

        if ($this->helper->getEnvironment() == Environment::ENVIRONMENT_PRODUCTION) {
            $script = $this->helper::SCRIPT_PRODUCTION;
        }

        /** Get color and size attributes */
        $sizeAttribute = null;
        $colorAttribute = null;

        try {
            $sizeAttribute = $this->productAttributeRepository->get($this->helper->getSizeAttribute())->getDefaultFrontendLabel();
        } catch (\Exception $e) {
            $this->logger->addError('[GET SIZE ATTRIBUTE FAIL][' . $e->getMessage() . ']');
        }

        try {
            $colorAttribute = $this->productAttributeRepository->get($this->helper->getColorAttribute())->getDefaultFrontendLabel();
        } catch (\Exception $e) {
            $this->logger->addError('[GET COLOR ATTRIBUTE FAIL][' . $e->getMessage() . ']');
        }

        $deferDays = $this->helper->getDeferredDays();

        $toshiData = [
            'toshiEnvironment' => $this->helper->getEnvironment(),
            'toshiMode' => $this->helper->getMode(),
            'toshiKey' => $this->helper->getClientKey(),
            'toshiUrl' => $this->helper->getUrl(),
            'toshiScript' => $script,
            'toshiSizeAttribute' => $sizeAttribute,
            'toshiColorAttribute' => $colorAttribute,
            'toshiTimeout' => $this->helper->getTimeoutAmount(),
        ];

        /** @var \Magento\Quote\Model\Quote */
        $cart = $this->session->getQuote();

        /** Get Cart Items */
        $items = $cart->getAllVisibleItems();

        $order = [
            'products' => []
        ];

        foreach ($items as $item) {
            $product = $item->getProduct();

            $productToAdd = [
                'name' => $item->getName(),
                'sku' => $item->getSku(),
                'qty' => $item->getQty(),
                'description' => $product->getDescription(),
                'additionalSizes' =>  $this->getSizes($product, $item->getSku()),
                'availabilityType' => 'immediate',
                'availabilityDate' => null
            ];

            $product->load('toshi_deferred_shipping');

            if ($product->getData('toshi_deferred_shipping') > $deferDays) {
                $deferDays = $product->getData('toshi_deferred_shipping');
            }

            $order['products'][] = $productToAdd;
        }

        $date = null;

        if ($deferDays) {
            $date = $this->calculateDate($deferDays);
            foreach($order['products'] as &$product) {
                $product['availabilityType'] = 'fixed';
                $product['availabilityDate'] = $date;
            }
        }

        $toshiData['toshiAvailabilityDate'] = $date;

        $toshiData['toshiData'] = $order;
        return $toshiData;
    }

    /**
     * @param ProductRepositoryInterface $product
     * @param string $sku Selected Product SKU
     * @return string[] Sizes Array
     */
    public function getSizes($product, $sku)
    {
        $sizeAttribute = $this->helper->getSizeAttribute();
        $sizes = [];

        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $childProducts = $product->getTypeInstance(true)->getUsedProducts($product);

            $allSizes = [];
            $currentSize = null;

            /** Get Current Size and collect All Sizes */
            foreach ($childProducts as $child) {
                if ($child->getSku() == $sku) {
                    $currentSize = $child->getData($sizeAttribute);
                }

                $allSizes[] = $child->getData($sizeAttribute);
            }

            /** Remove Duplicates */
            $allSizes = array_unique($allSizes);

            /** What Sizes are allowed */
            $allowedSizes = [];

            /** Pick out next and previous size */
            foreach ($allSizes as $size) {
                if ($size == $currentSize) {
                    $currentSizeIndex = array_search($currentSize, $allSizes);
                    if ($currentSizeIndex !== FALSE) {
                        /** Prev Size */
                        if (isset($allSizes[$currentSizeIndex - 1])) {
                            $allowedSizes[] = $allSizes[$currentSizeIndex - 1];
                        }

                        /** Next Size */
                        if (isset($allSizes[$currentSizeIndex + 1])) {
                            $allowedSizes[] = $allSizes[$currentSizeIndex + 1];
                        }
                    }
                }
            }

            /** Get products for sizes that are allowed */
            foreach ($childProducts as $child) {
                if ($child->isSaleable() && $child->getSku() != $sku && in_array($child->getData($sizeAttribute), $allowedSizes)) {
                    $sizes[] = [
                        'variantSku' => $child->getSku(),
                        'size' => $child->getAttributeText($sizeAttribute),
                        'isAvailable' => true
                    ];
                }
            }
        }

        return $sizes;
    }

    public function calculateDate($days) {

        $date = date('d/m/Y', strtotime("+{$days} days"));
        $holidays = $this->helper->getHolidays();

        while(!$this->isValidDate($date, $holidays)) {
            $days++;
            $date = date('d/m/Y', strtotime("+{$days} days"));
        }

        $date = DateTime::createFromFormat("d/m/Y", $date);
        return $date->format(\DateTimeInterface::RFC3339_EXTENDED);
    }

    private function isValidDate($date, $holidays) {
        $date = DateTime::createFromFormat("d/m/Y", $date);

        /** Check Holidays */
        foreach ($holidays as $holiday) {
            if ($date->format('d/m') == $holiday->date) {
                return false;
            }
        }

        /** Is Weekend */
        if ($date->format('w') == '0' || $date->format('w') == '6') {
            return false;
        }


        return true;
    }
}
