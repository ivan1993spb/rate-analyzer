#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleSource;
use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleAggregator;
use CoinCorp\RateAnalyzer\Correlation\Correlation;
use Commando\Command;
use Monolog\Logger;

$cmd = new Command();
$cmd->option('c')->aka('config')->describedAs('Config file')->required()->file();
$cmd->option('o')->aka('output-xlsx')->describedAs('Output XLSX file name')->required();
$cmd->option('e')->aka('extended')->describedAs('Generate extended correlation table')->boolean()->default(false);

$config = require $cmd['config'];

$logger = new Logger('logger');
$aggregator = new CandleAggregator($logger, (integer)$config['cache-size']);

foreach ($config['sources'] as $source) {
    if ($source instanceof CandleSource) {
        if ($config['candle-size'] > 1) {
            $aggregator->addCandleEmitter(new CandleBatcher($source, (integer)$config['candle-size']));
        } else {
            $aggregator->addCandleEmitter($source);
        }
    }
}

$XLSXFile = $cmd['output-xlsx'];
$extended = $cmd['extended'];

$corr = new Correlation($aggregator, $logger, $XLSXFile, $extended);
$corr->findCorrelation();
