#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleSource;
use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleAggregator;
use CoinCorp\RateAnalyzer\Correlation\Correlation;
use Commando\Command;
use Monolog\Logger;

ini_set("trader.real_precision", 10);
ini_set('memory_limit', '-1');

$cmd = new Command();

$cmd->setHelp('Calculate correlation and generate XLSX table');

$cmd->option('s')->aka('sources')->describedAs('Config file with list of sources')->required()->file();
$cmd->option('o')->aka('output-xlsx')->describedAs('Output XLSX file name')->required();
$cmd->option('e')->aka('extended')->describedAs('Generate extended correlation table')->boolean()->default(false);
$cmd->option('b')->aka('batch-size')->describedAs('Candles number to batch')->default(1)->must(function($value) {
    return is_numeric($value) && $value > 0;
});

$sources = require $cmd['sources'];

$logger = new Logger('logger');
$aggregator = new CandleAggregator($logger);

foreach ($sources as $source) {
    if ($source instanceof CandleSource) {
        if ($cmd['batch-size'] > 1) {
            $aggregator->addCandleEmitter(new CandleBatcher($source, (integer)$cmd['batch-size']));
        } else {
            $aggregator->addCandleEmitter($source);
        }
    } else {
        $logger->warn('Invalid source type', ['source' => $source]);
    }
}

$XLSXFile = $cmd['output-xlsx'];
$extended = $cmd['extended'];

$corr = new Correlation($aggregator, $logger, $XLSXFile, $extended);
$corr->findCorrelation();
