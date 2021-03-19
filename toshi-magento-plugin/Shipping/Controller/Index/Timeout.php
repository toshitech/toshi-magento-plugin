<?php

namespace Toshi\Shipping\Controller\Index;

use \Magento\Framework\App\Action\Context;
use \Magento\Framework\Message\ManagerInterface;
use \Magento\Framework\Controller\ResultFactory;

class Timeout extends \Magento\Framework\App\Action\Action
{

    /**
     * @var ManagerInterface
     */
    public $messageManager;

    public function __construct(
        Context $context,
        ManagerInterface $messageManager
    ) {
        $this->messageManager = $messageManager;

        return parent::__construct($context);
    }

    public function execute()
    {
        $this->messageManager->addWarningMessage('Your checkout session has expired');
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($this->_url->getUrl('checkout/cart', ['_secure' => true]));

        return $resultRedirect;
    }
}
