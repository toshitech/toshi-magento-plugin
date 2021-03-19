<?php
namespace Toshi\Shipping\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Toshi\Shipping\Helper\Data;
use Toshi\Shipping\Logger\Logger;

use Magento\OfflinePayments\Model\Cashondelivery;
use Toshi\Shipping\Model\Adminhtml\Source\Mode;

class PaymentMethodActive implements ObserverInterface
{

    /**
     * @var Data
     */
    protected $helper;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(
        Data $helper,
        Logger $logger
    )
    {
        $this->helper = $helper;
        $this->logger = $logger;
    }
    /**
     * Below is the method that will fire whenever the event runs
     *
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if ($observer->getEvent()->getQuote() != null) {

            if ($this->helper->getMode() == MODE::MODE_TRY_BEFORE_YOU_BUY) {
                $quote = $observer->getEvent()->getQuote();
                $shippingMethod = $quote->getShippingAddress()->getShippingMethod();
                $paymentMethod = $observer->getEvent()->getMethodInstance();
                $result = $observer->getEvent()->getResult();

                if ($paymentMethod->getCode() != Cashondelivery::PAYMENT_METHOD_CASHONDELIVERY_CODE && $shippingMethod == $this->helper::SHIPPING_METHOD) {
                    $result->setData('is_available', false);
                }
            }
        }
    }
}
