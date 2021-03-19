<?php
namespace Toshi\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Toshi\Shipping\Helper\Data;
use Magento\Catalog\Model\Product\Attribute\Repository;
use Toshi\Shipping\Logger\Logger;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;

class ConfirmOrder implements ObserverInterface
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var Repository
     */
    protected $productAttributeRepository;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(
        Data $helper,
        Repository $productAttributeRepository,
        Logger $logger
    )
    {
        $this->helper = $helper;
        $this->productAttributeRepository = $productAttributeRepository;
        $this->logger = $logger;
    }

    /**
     * Below is the method that will fire whenever the event runs!
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var OrderInterface $order */
            $order = $observer->getEvent()->getOrder();

            $shippingMethod = $order->getShippingMethod();

            /** Ignore if shipping method is not Toshi */
            if ($shippingMethod != $this->helper::SHIPPING_METHOD) {
                return;
            }

            /** Get QuoteId as Store Checkout Reference */
            $storeCheckoutReference = $order->getQuoteId();

            /** @var OrderAddressInterface */
            $shippingAddress = $order->getShippingAddress();

            /** @var OrderAddressInterface */
            $billingAddress = $order->getBillingAddress();

            $lineItems = [];


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

            foreach ($order->getAllVisibleItems() as $item) {
                $colour = $this->getAttribute($item['product_options']['attributes_info'], $colorAttribute);
                $size = $this->getAttribute($item['product_options']['attributes_info'], $sizeAttribute);

                $lineItems[] = [
                    'name' => $item->getName(),
                    'sku' => $item->getSku(),
                    'retail_price' => (int) $item->getPrice(),
                    'promotion_price' => 0,
                    'markdown_price' => 0,
                    'final_price' => (int) $item->getPriceInclTax(),
                    'colour' => $colour,
                    'size' => $size,
                    'qty' => (int) $item->getQtyOrdered(),

                    'gender' => 'N/A',
                    'season' => 'N/A',
                    'product_category' => 'N/A',
                    'product_subcategory' => 'N/A',
                    'description' => 'N/A',
                    'variant_sku' => 'N/A',
                    'promotion_id' => 'N/A',
                    'availability_date' => 'N/A',
                    'image_url' => 'N/A',
                    'product_url' => 'N/A',
                ];
            }

            $orderData = [
                'line_items' => $lineItems,
                'customer' => [
                    'first_name' => $shippingAddress->getFirstName(),
                    'surname' => $shippingAddress->getLastName(),
                    'email' => $shippingAddress->getEmail(),
                    'phone' => $shippingAddress->getTelephone()
                ],
                'billing_address' => [
                    'address_line_1' => $billingAddress->getStreet(),
                    'town' => $billingAddress->getCity(),
                    'province' => 'N/A',
                    'postcode' => $billingAddress->getPostcode(),
                    'country' => $billingAddress->getCountryId()
                ],
                'shipping_address' => [
                    'address_line_1' => $shippingAddress->getStreet(),
                    'town' => $shippingAddress->getCity(),
                    'province' => 'N/A',
                    'postcode' => $shippingAddress->getPostcode(),
                    'country' => $shippingAddress->getCountryId()
                ],
                'brand_checkout_reference' => $storeCheckoutReference,
                'brand_order_reference' => $order->getIncrementId()
            ];

            $this->helper->confirmOrder($orderData);
            $this->logger->addInfo('[ORDER CONFIRM OBSERVER][SUCCESS][' . $order->getIncrementId() . ']');
        } catch (\Exception $e) {
            /** Log failure */
            $this->logger->addError('[ORDER CONFIRM OBSERVER][FAIL][' . $order->getIncrementId() . '][' . $e->getMessage() . ']');
        }

        return;
    }

    public function getAttribute($itemOptions, $label)
    {
        $attribute = '';

        if ($label) {
            foreach ($itemOptions as $itemOption) {
                if ($itemOption['label'] == $label) {
                    $attribute = $itemOption['value'];
                    break;
                }
            }
        }

        return $attribute;
    }
}

