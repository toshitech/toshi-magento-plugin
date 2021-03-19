<?php
namespace Toshi\Shipping\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Psr\Log\LoggerInterface;
use Magento\Payment\Model\Config as PaymentConfig;
use Magento\Customer\Api\AddressRepositoryInterface;
use Toshi\Shipping\Logger\Logger;

use Toshi\Shipping\Helper\Data;
use Toshi\Shipping\Model\Adminhtml\Source\Mode;
use Magento\Quote\Api\Data\CartInterface;

class Shipping extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'toshi';

    /**
     * @var boolean
     */
    protected $_isFixed = true;

    /**
     * @var ResultFactory
     */
    protected $rateResultFactory;

    /**
     * @var MethodFactory
     */
    protected $rateMethodFactory;

    /**
     * @var PaymentConfig
     */
    protected $paymentConfig;

    /**
     * @var AddressRepositoryInterface
     */
    protected $addressRepository;

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var Logger
     */
    protected $toshiLogger;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        PaymentConfig $paymentConfig,
        AddressRepositoryInterface $addressRepositoryInterface,
        Data $helper,
        Logger $toshiLogger,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->paymentConfig = $paymentConfig;
        $this->addressRepository = $addressRepositoryInterface;
        $this->helper = $helper;
        $this->toshiLogger = $toshiLogger;
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @inheritdoc
     */
    public function getAllowedMethods()
    {
        return [$this->getCarrierCode() => __($this->getConfigData('name'))];
    }

    /**
     * @inheritdoc
     */
    public function collectRates(RateRequest $request)
    {
        /** Array of active methods */
        $activePaymentMethods = $this->paymentConfig->getActiveMethods();

        /** Do not show Toshi when try before you buy is active BUT Cash on Delivery is not */
        if ($this->helper->getMode() === Mode::MODE_TRY_BEFORE_YOU_BUY && !array_key_exists('cashondelivery', $activePaymentMethods)) {
            return false;
        }

        $toshiMinBasketAmount = $this->helper->getMinBasketAmount();
        $taxDefaultPostcode = $this->helper->getTaxDefaultPostcode();

        /** Check fields */
        if (!$this->fieldsCheck($request)) {
            return false;
        }

        /** Check default tax code to see if it matches destination postcode */
        if ($taxDefaultPostcode == $request->getDestPostcode()) {
            return false;
        }

        /** Are we below the min basket amount? */
        if ($request->getPackageValue() < (int) ($toshiMinBasketAmount)) {
            return false;
        }

        /** Format address to send to Toshi */
        $address = [
            'postcode' => $request->getDestPostcode(),
            'address_line_1' => $request->getDestStreet(),
            'address_line_2' => 'N/A',
            'town' => $request->getDestCity(),
            'country' => $request->getDestCountryId(),
        ];

        /** Is the method active and is Address Eligible? */
        if (!$this->isActive() || !$this->helper->isAddressEligible($address)) {
            return false;
        }

        /** Check Items */
        $items = $request->getAllItems();
        foreach($items as $item) {
            /** @var CartInterface $quote */
            $product = $item->getProduct();
            $product->load('available_for_toshi');

            if (!$product->getAvailableForToshi()) {
                return false;
            }
        }

        /** Create Toshi Result */
        $result = $this->rateResultFactory->create();
        $shippingPrice = $this->getConfigData('price');

        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->getCarrierCode());
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod($this->getCarrierCode());
        $method->setMethodTitle($this->getConfigData('name'));

        $method->setPrice($shippingPrice);
        $method->setCost($shippingPrice);

        $result->append($method);
        return $result;
    }
    
    /**
     * Check Fields if we have a phone number or if we are on payment.
     * @param RateRequest $request
     * @return boolean
     */
    private function fieldsCheck(RateRequest $request)
    {
        /** On payment option selection, we can return true */
        if ($request->getPaymentMethod()) {
            return true;
        }

        $quote = null;
        $items = $request->getAllItems();
        foreach($items as $item) {
            /** @var CartInterface $quote */
            $quote = $item->getQuote();
            break;
        }

        $shippingAddress = $quote->getShippingAddress();
        return !! $shippingAddress->getTelephone();
    }
}
