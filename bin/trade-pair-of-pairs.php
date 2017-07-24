#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleAggregator;
use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleSource;
use CoinCorp\RateAnalyzer\ExchangeStateSlice;
use Commando\Command;
use Monolog\Logger;

ini_set("trader.real_precision", 10);

//
// Constants
//

define("DEFAULT_PERIOD_SMA_RATIO", 60*24*7);

define("CANDLE_TRADE_PAIR_FIRST", 0);
define("CANDLE_TRADE_PAIR_SECOND", 1);

define("CACHE_SIZE_DEVIATION", 18);

// Графики на временном срезе
define("CHART_CLOSE_PRICE_FIRST_PAIR", 0);
define("CHART_CLOSE_PRICE_SECOND_PAIR", 1);
define("CHART_DEVIATION", 2);
define("CHART_REAL_PAIRS_DIFF", 3);
define("CHART_REAL_RATIO_DIVIDE_RATIO_SMA", 4);
define("CHART_SIGMA", 5);

//
// Commands
//

$cmd = new Command();
$cmd->option('s')->aka('sources')->describedAs('Config file with 2 sources')->required()->file();
$cmd->option('b')->aka('batch-size')->describedAs('Candles number to batch')->default(1)->must(function($value) {
    return is_numeric($value) && $value > 0;
});
$cmd->option('from')->describedAs('Time UTC')->default(new DateTime("0001-01-01", new DateTimeZone('UTC')))->cast(function($value) {
    return new DateTime($value, new DateTimeZone('UTC'));
});
$cmd->option('to')->describedAs('Time UTC')->default(new DateTime('now', new DateTimeZone('UTC')))->cast(function($value) {
    return new DateTime($value, new DateTimeZone('UTC'));
});
$cmd->option('period-sma-ratio')->describedAs('Period for SMA(pairs ratio) in minutes')->default(DEFAULT_PERIOD_SMA_RATIO)->must(function($value) {
    return is_integer($value) && $value > 1 && $value <= 100000;
});
// TODO: Использовать эти значения.
$cmd->option('first-fee')->describedAs('First pair trade fee')->default(0)->must(function($value) {
    return is_double($value);
});
$cmd->option('second-fee')->describedAs('Second pair trade fee')->default(0)->must(function($value) {
    return is_double($value);
});

/** @var \CoinCorp\RateAnalyzer\CandleEmitterInterface[] $sources */
$sources = require $cmd['sources'];
/** @var integer $batchSize */
$batchSize = intval($cmd['batch-size']);
/** @var \DateTime $from */
$from = $cmd['from'];
/** @var \DateTime $to */
$to = $cmd['to'];
// За какой период считать средние соотношение соотношение курсов
/** @var integer $periodRatioSMA */
$periodRatioSMA = intval($cmd['period-sma-ratio']/$batchSize);
$cacheSizeRatio = $periodRatioSMA;
$cacheSizeRatioTail = $periodRatioSMA - 1;
// TODO: Использовать эти значения.
/** @var double $firstPairFee */
$firstPairFee = doubleval($cmd['first-fee']);
/** @var double $secondPairFee */
$secondPairFee = doubleval($cmd['second-fee']);

//
// Init logging
//

$logger = new Logger('logger');
$logger->info("Start calculation");

//
// Init aggregator
//

$aggregator = new CandleAggregator($logger);

foreach ($sources as $source) {
    if ($source instanceof CandleSource) {
        if ($batchSize > 1) {
            $aggregator->addCandleEmitter(new CandleBatcher($source, $batchSize));
        } else {
            $aggregator->addCandleEmitter($source);
        }
    }
}

if ($aggregator->capacity() !== 2) {
    $logger->error("Invalid aggregator capacity !== 2");
    exit(1);
}

$generator = $aggregator->rows();
if (!$generator->valid()) {
    $logger->warn("Empty aggregator");
    exit(1);
}

/** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
$dataRow = $generator->current();
$firstPair = $dataRow->candles[CANDLE_TRADE_PAIR_FIRST];
$secondPair = $dataRow->candles[CANDLE_TRADE_PAIR_SECOND];
$logger->info("Pair labels", [
    'first_pair_label'  => $firstPair->label,
    'second_pair_label' => $secondPair->label,
]);

//
// Cache ratio values
//

$logger->info("Cache ratio values");

/** @var float[] $tailRatioPrices */
$tailRatioPrices = [];

/** @var float[] $ratioPrices */
$ratioPrices = [];

for ($i = 0; $i < $cacheSizeRatioTail+$cacheSizeRatio; $i++) {
    // *** Begin cache ratio ***
    /** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
    $dataRow = $generator->current();
    $firstPair = $dataRow->candles[CANDLE_TRADE_PAIR_FIRST];
    $secondPair = $dataRow->candles[CANDLE_TRADE_PAIR_SECOND];
    $ratio = $firstPair->close / $secondPair->close;
    array_push($ratioPrices, $ratio);
    while (sizeof($ratioPrices) > $cacheSizeRatio) {
        array_push($tailRatioPrices, array_shift($ratioPrices));
        if (sizeof($tailRatioPrices) > $cacheSizeRatioTail) {
            array_shift($tailRatioPrices);
        }
    }
    // *** End cache ratio ***

    // *** Begin finalize iteration ***
    $generator->next();
    if (!$generator->valid()) {
        $logger->warn("Closed generator");
        exit(1);
    }
    // *** End finalize iteration ***
}

//
// Init time slice
//

$slice = new ExchangeStateSlice;
/** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
$dataRow = $generator->current();
$firstPair = $dataRow->candles[CANDLE_TRADE_PAIR_FIRST];
$secondPair = $dataRow->candles[CANDLE_TRADE_PAIR_SECOND];
$slice->seriesNames = [
    CHART_CLOSE_PRICE_FIRST_PAIR      => sprintf("Close price %s", $firstPair->label),
    CHART_CLOSE_PRICE_SECOND_PAIR     => sprintf("Close price %s", $secondPair->label),
    CHART_DEVIATION                   => sprintf("Deviation %d", $periodRatioSMA),
    CHART_REAL_PAIRS_DIFF             => "Real diff",
    CHART_REAL_RATIO_DIVIDE_RATIO_SMA => sprintf("Ratio/SMA_%d(Ratio)", $periodRatioSMA),
    CHART_SIGMA                       => sprintf("Sigma Ratio and SMA_%d(Ratio)", $periodRatioSMA),
];
$slice->series = array_fill(0, sizeof($slice->seriesNames), []);

//
// Calculate statistics
//

/** @var float[] $deviations */
$deviations = [];

while ($generator->valid()) {
    // *** Begin prepare ratio caches ***
    /** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
    $dataRow = $generator->current();
    $firstPair = $dataRow->candles[CANDLE_TRADE_PAIR_FIRST];
    $secondPair = $dataRow->candles[CANDLE_TRADE_PAIR_SECOND];
    /** @var float $ratio */
    $ratio = $firstPair->close / $secondPair->close;
    array_push($ratioPrices, $ratio);
    while (sizeof($ratioPrices) > $cacheSizeRatio) {
        array_push($tailRatioPrices, array_shift($ratioPrices));
        array_shift($tailRatioPrices);
    }
    // *** End prepare ratio caches ***

    // *** Begin calculate sigma, deviation and SMA(ratio prices) ***
    /** @var float[] $ratioPricesSMA */
    $ratioPricesSMA = array_values(trader_sma(array_merge($tailRatioPrices, $ratioPrices), $periodRatioSMA));
    /** @var float $ratioSMA */
    $ratioSMA = $ratioPricesSMA[sizeof($ratioPricesSMA)-1];
    /** @var float $sum */
    $sum = 0.0;
    for ($i = 0; $i < sizeof($ratioPrices); $i++) {
        $sum += pow($ratioPrices[$i]-$ratioPricesSMA[$i], 2);
    }
    $sigma = sqrt($sum/sizeof($ratioPrices));
    $deviation = ($ratio-$ratioSMA) / $sigma;
    array_push($deviations, $deviation);
    while (sizeof($deviations) > CACHE_SIZE_DEVIATION) {
        array_shift($deviations);
    }
    $logger->info("Stats", [
        'ratio'     => $ratio,
        'ratioSMA'  => $ratioSMA,
        'sigma'     => $sigma,
        'deviation' => $deviation,
    ]);
    // *** End calculate sigma, deviation and SMA(ratio) ***

    // *** Begin check time range ***
    if ($dataRow->time->getTimestamp() < $from->getTimestamp()) {
        $generator->next();
        continue;
    }
    if ($dataRow->time->getTimestamp() > $to->getTimestamp()) {
        break;
    }
    // *** End check time range ***

    // *** Begin create charts ***
    array_push($slice->series[CHART_CLOSE_PRICE_FIRST_PAIR], [
        'start' => $dataRow->time->getTimestamp(),
        'price' => $firstPair->close,
    ]);
    array_push($slice->series[CHART_CLOSE_PRICE_SECOND_PAIR], [
        'start' => $dataRow->time->getTimestamp(),
        'price' => $secondPair->close,
    ]);
    array_push($slice->series[CHART_DEVIATION], [
        'start' => $dataRow->time->getTimestamp(),
        'price' => $deviation,
    ]);
    array_push($slice->series[CHART_REAL_PAIRS_DIFF], [
        'start' => $dataRow->time->getTimestamp(),
        'price' => $firstPair->close-$secondPair->close,
    ]);
    array_push($slice->series[CHART_REAL_RATIO_DIVIDE_RATIO_SMA], [
        'start' => $dataRow->time->getTimestamp(),
        'price' => $ratio/$ratioSMA,
    ]);
    array_push($slice->series[CHART_SIGMA], [
        'start' => $dataRow->time->getTimestamp(),
        'price' => $sigma,
    ]);
    // *** Begin create charts ***

    // *** Begin finalize iteration ***
    $generator->next();
    if (!$generator->valid()) {
        $logger->info("Generator closed");
        break;
    }
    // *** End finalize iteration ***
}

//
// Print time slice
//

file_put_contents("php://stdout", json_encode($slice));
