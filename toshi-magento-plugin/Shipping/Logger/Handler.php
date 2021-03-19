<?php

namespace Toshi\Shipping\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger as MonologLogger;

class Handler extends Base {

    /**
     * @var int $loggerType Logging Level
     */
    protected $loggerType =  MonologLogger::INFO;

    /** 
     * @var String $fileName
     */
    public $fileName = '/var/log/toshi.log';
}
