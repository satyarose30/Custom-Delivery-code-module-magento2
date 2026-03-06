<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

/**
 * Routes all Custom_DeliveryRestriction log messages to a dedicated file,
 * keeping them separate from the main system.log.
 */
class Handler extends Base
{
    /** @var int Minimum log level — INFO and above */
    protected $loggerType = Logger::INFO;

    /** @var string Dedicated log file path */
    protected $fileName = '/var/log/custom/delivery_restriction.log';
}
