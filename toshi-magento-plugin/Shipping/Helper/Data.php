<?php

namespace Toshi\Shipping\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Toshi\Shipping\Logger\Logger;

class Data extends AbstractHelper
{
    /**
     * @var Curl
     */
    protected $curlClient;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

        /**
     * @var Logger
     */
    protected $logger;

    const MODE = 'carriers/toshi/mode';
    const ENVIRONMENT = 'carriers/toshi/environment';
    const CLIENT_API_KEY = 'carriers/toshi/toshi_client_api_key';
    const SERVER_API_KEY = 'carriers/toshi/toshi_server_api_key';
    const ENDPOINT_URL = 'carriers/toshi/toshi_endpoint_url';
    const MIN_BASKET_AMOUNT = 'carriers/toshi/toshi_min_basket_amount';
    const SIZE_ATTRIBUTE = 'carriers/toshi/size_attribute';
    const COLOR_ATTRIBUTE = 'carriers/toshi/color_attribute';
    const TIMEOUT = 'carriers/toshi/timeout';
    const HOLIDAYS = 'carriers/toshi/holidays';
    const DEFERRED_DAYS = 'carriers/toshi/deferred_days';
    const TAX_POSTCODE = 'tax/defaults/postcode';

    const ADDRESS_ELIGIBLE_ENDPOINT = '/v2/address/eligible';
    const CONFIRM_ORDER_ENDPOINT = '/v2/order/confirm_store_order';

    const SHIPPING_METHOD = 'toshi_toshi';

    const SCRIPT_SANDBOX = 'https://integration-sandbox-cdn.toshi.co/3.0/main.min.js';
    const SCRIPT_PRODUCTION = 'https://integration-cdn.toshi.co/3.0/main.min.js';

    /**
     * @param Context $context
     * @param Curl $curlClient
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        Curl $curlClient,
        ScopeConfigInterface $scopeConfig,
        Logger $logger
    )
    {
        $this->curlClient = $curlClient;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;

        parent::__construct($context);
    }

    /**
     * @param $path
     * @return mixed
     */
    private function getConfigValue($path)
    {
        return $this->scopeConfig->getValue($path);
    }

    /**
     * @return string Mode
     */
    public function getMode()
    {
        return $this->getConfigValue(self::MODE);
    }

    /**
     * @return string Environment
     */
    public function getEnvironment()
    {
        return $this->getConfigValue(self::ENVIRONMENT);
    }

    /**
     * @return string Client Key
     */
    public function getClientKey()
    {
        return $this->getConfigValue(self::CLIENT_API_KEY);
    }

    /**
     * @return string Server Key
     */
    public function getServerKey()
    {
        return $this->getConfigValue(self::SERVER_API_KEY);
    }

    /**
     * @return string Get URL
     */
    public function getUrl()
    {
        return $this->getConfigValue(self::ENDPOINT_URL);
    }

    /**
     * @return float Get Min Basket Amount
     */
    public function getMinBasketAmount()
    {
        return $this->getConfigValue(self::MIN_BASKET_AMOUNT);
    }

    /**
     * @return string Get Size Attribute
     */
    public function getSizeAttribute()
    {
        return $this->getConfigValue(self::SIZE_ATTRIBUTE);
    }

    /**
     * @return string Get Color Attribute
     */
    public function getColorAttribute()
    {
        return $this->getConfigValue(self::COLOR_ATTRIBUTE);
    }

    /**
     * @return string[] Get Holidays
     */
    public function getHolidays()
    {
        if (!empty($this->getConfigValue(self::HOLIDAYS))) {
            return (array) json_decode($this->getConfigValue(self::HOLIDAYS));
        }

        return [];
    }

    /**
     * @return int Get Timeout Amount
     */
    public function getTimeoutAmount()
    {
        return $this->getConfigValue(self::TIMEOUT);
    }

    /**
     * @return int Get Deferred Days
     */
    public function getDeferredDays()
    {
        return $this->getConfigValue(self::DEFERRED_DAYS);
    }

    /**
     * @return string Get Tax Default Postcode
     */
    public function getTaxDefaultPostcode()
    {
        return $this->getConfigValue(self::TAX_POSTCODE);
    }

    /**
     * @param string[] $address Formatted Address for Toshi
     * @return boolean
     */
    public function isAddressEligible($address)
    {
        try {
            $endpoint =  $this->getUrl() . self::ADDRESS_ELIGIBLE_ENDPOINT;
            $toshiApiKey = $this->getServerKey();

            /** Set Headers + Key for Toshi */
            $this->curlClient->addHeader('Content-Type', 'application/json');
            $this->curlClient->addHeader('X-Toshi-Server-Api-Key', $toshiApiKey);

            /** Send request */
            $this->curlClient->post($endpoint, json_encode($address));

            /** Decode it */
            $response = json_decode($this->curlClient->getBody());

            if (array_key_exists('eligible', $response)) {
                return $response->eligible;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            /** Log failure */
            $this->logger->addError('[ADDRESS ELIGIBILITY CHECK][FAIL][' . $e->getMessage() . ']');
            return false;
        }
    }

    /**
     * @param string[] $order Formatted Order for Toshi
     * @return boolean
     */
    public function confirmOrder($order)
    {
        try {
            $endpoint =  $this->getUrl() . self::CONFIRM_ORDER_ENDPOINT;
            $toshiApiKey = $this->getServerKey();

            /** Set Headers + Key for Toshi */
            $this->curlClient->addHeader('Content-Type', 'application/json');
            $this->curlClient->addHeader('X-Toshi-Server-Api-Key', $toshiApiKey);

            /** Send request */
            $this->curlClient->post($endpoint, json_encode($order));

            /** Decode it */
            $response = json_decode($this->curlClient->getBody());
            return true;
        } catch (\Exception $e) {
            /** Log failure */
            $this->logger->addError('[ORDER CONFIRM REQUEST][FAIL][' . $e->getMessage() . ']');
            return false;
        }
    }

}