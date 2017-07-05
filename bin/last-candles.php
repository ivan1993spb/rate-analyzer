#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleEmitterInterface;
use Commando\Command;
use Monolog\Logger;

$cmd = new Command();
$cmd->setHelp('Generates report with N last candles for all sources');

$cmd->option('s')->aka('sources')->describedAs('Config file with list of sources')->required()->file();
$cmd->option('b')->aka('batch-size')->describedAs('Candles number to batch')->default(1)->must(function($value) {
    return is_numeric($value) && $value > 0;
});
$cmd->option('n')->aka('number')->describedAs('Candles number')->default(1)->must(function($value) {
    return is_numeric($value) && $value > 0;
});

/** @var \CoinCorp\RateAnalyzer\CandleEmitterInterface[] $sources */
$sources = require $cmd['sources'];

$logger = new Logger('logger');

echo "\n";
echo "# Last candles\n";
echo "\n";

foreach ($sources as $emitter) {
    if ($emitter instanceof CandleEmitterInterface) {
        if ($cmd['batch-size'] > 1) {
            $emitter = new CandleBatcher($emitter, (integer)$cmd['batch-size']);
        }

        echo "## ", $emitter->getName(), "\n";
        echo "\n";
        echo "Candle size = ", $emitter->getCandleSize(), " seconds\n";
        echo "\n";

        /** @var \CoinCorp\RateAnalyzer\Candle[] $candles */
        $candles = [];
        foreach ($emitter->candles() as $candle) {
            array_push($candles, $candle);
            while (sizeof($candles) > $cmd['number']) {
                array_shift($candles);
            }
        }

        foreach ($candles as $candle) {
            printf("* `%s`, close = `%f`, volume = `%f`, trades = `%d`\n", $candle->start->format('r'), $candle->close,
                $candle->volume, $candle->trades);
        }

        echo "\n";
    } else {
        $logger->warn("Invalid source type");
    }
}