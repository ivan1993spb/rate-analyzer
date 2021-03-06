#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleEmitterInterface;
use Commando\Command;
use Monolog\Logger;

$cmd = new Command();
$cmd->option('s')->aka('sources')->describedAs('Config file with list of sources')->required()->file();
$cmd->option('b')->aka('batch-size')->describedAs('Candles number to batch')->default(1)->must(function($value) {
    return is_numeric($value) && $value > 0;
});

/** @var \CoinCorp\RateAnalyzer\CandleEmitterInterface[] $sources */
$sources = require $cmd['sources'];

$logger = new Logger('logger');

echo "\n";
echo "# Latest prices\n";
echo "\n";

foreach ($sources as $emitter) {
    if ($emitter instanceof CandleEmitterInterface) {
        if ($cmd['batch-size'] > 1) {
            $emitter = new CandleBatcher($emitter, (integer)$cmd['batch-size']);
        }

        /** @var \CoinCorp\RateAnalyzer\Candle $candle */
        $candle = null;
        foreach ($emitter->candles() as $candle) {
        }

        printf("* `%s` - **%s** - `%f`\n", $candle->start->format('r'), $emitter->getName(), $candle->open);
    } else {
        $logger->warn("Invalid source type");
    }
}
