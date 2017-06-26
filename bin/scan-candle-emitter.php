#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleEmitterInterface;
use CoinCorp\RateAnalyzer\Scanners\CandleEmitterScanner;
use Commando\Command;
use Monolog\Logger;

$cmd = new Command();
$cmd->option('c')->aka('config')->describedAs('Config file')->required()->file();

$config = require $cmd['config'];

$logger = new Logger('logger');

foreach ($config['sources'] as $emitter) {
    if ($emitter instanceof CandleEmitterInterface) {
        $scanner = new CandleEmitterScanner($emitter, $logger);
        $generator = $scanner->scan();
        if (!$generator->valid()) {
            $logger->info("Ranges not found");
        } else {
            foreach ($generator as $range) {
                $logger->info("Range", ['start' => $range->start->format('r'), 'finish' => $range->finish->format('r')]);
            }
        }
    }
}
