<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;

class CustomizePppLogger
{
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(new WebProcessor);
            $handler->pushProcessor(new IntrospectionProcessor);
            $handler->pushProcessor(function ($record) {
                $record['extra']['server'] = gethostname();
                $record['extra']['php_version'] = PHP_VERSION;
                return $record;
            });
        }
    }
}