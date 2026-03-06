<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Logger;

use Monolog\Logger as MonologLogger;

/**
 * Dedicated logger for Custom_DeliveryRestriction.
 * Wired to Handler via di.xml so all module logs land in
 * var/log/custom/delivery_restriction.log.
 *
 * Usage:
 *   inject \Custom\DeliveryRestriction\Logger\Logger
 *   $this->logger->info('message', ['context' => $data]);
 */
class Logger extends MonologLogger
{
    // Inherits all PSR-3 log-level methods from Monolog.
    // Custom name used to distinguish this channel in the log file.
}
