#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleAggregator;
use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleSource;
use CoinCorp\RateAnalyzer\Chart;
use CoinCorp\RateAnalyzer\ChartPoint;
use CoinCorp\RateAnalyzer\ExchangeStateSlice;
use Commando\Command;
use Monolog\Logger;

ini_set("trader.real_precision", 10);

//
// Constants
//

define("DEFAULT_PERIOD_SMA_RATIO", 60*24*7);
define("DEFAULT_BERIOD_BANDS", 5);


define("CANDLE_TRADE_PAIR_FIRST", 0);
define("CANDLE_TRADE_PAIR_SECOND", 1);

// Графики на временном срезе
define("CHART_CLOSE_PRICE_FIRST_PAIR", 0);
define("CHART_CLOSE_PRICE_SECOND_PAIR", 1);
define("CHART_DEVIATION", 2);
define("CHART_REAL_PAIRS_DIFF", 3);
define("CHART_REAL_RATIO_DIVIDE_RATIO_SMA", 4);
define("CHART_SIGMA", 5);

// Данные
define("CHART_DEVIATION_SERIE_DEVIATION", 0);
define("CHART_DEVIATION_SERIE_DEVIATION_LOWER_BAND", 1);
define("CHART_DEVIATION_SERIE_DEVIATION_SMA", 2);
define("CHART_DEVIATION_SERIE_DEVIATION_UPPER_BAND", 3);

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
$cmd->option('bbands-period')->describedAs('Bollinger Bands period for deviation')->default(DEFAULT_BERIOD_BANDS);

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
/** @var integer $bbandsPeriod */
$bbandsPeriod = intval($cmd['bbands-period']);
$deviationsCacheSize = $bbandsPeriod;

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

/** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
$dataRow = $generator->current();
$firstPair = $dataRow->candles[CANDLE_TRADE_PAIR_FIRST];
$secondPair = $dataRow->candles[CANDLE_TRADE_PAIR_SECOND];

$slice = new ExchangeStateSlice;
$slice->charts[CHART_CLOSE_PRICE_FIRST_PAIR] = new Chart(sprintf("Close price %s", $firstPair->label), [$firstPair->label]);
$slice->charts[CHART_CLOSE_PRICE_SECOND_PAIR] = new Chart(sprintf("Close price %s", $secondPair->label), [$secondPair->label]);
$slice->charts[CHART_DEVIATION] = new Chart(sprintf("Deviation %d", $periodRatioSMA), [
    'Deviation',
    'Lower band',
    'Deviation SMA',
    'Upper band',
]);
$slice->charts[CHART_REAL_PAIRS_DIFF] = new Chart("Real diff", ["Real diff"]);
$slice->charts[CHART_REAL_RATIO_DIVIDE_RATIO_SMA] = new Chart(sprintf("Ratio/SMA_%d(Ratio)", $periodRatioSMA), ["Ratio/SMA"]);
$slice->charts[CHART_SIGMA] = new Chart(sprintf("Sigma Ratio and SMA_%d(Ratio)", $periodRatioSMA), ["Sigma Ratio/SMA"]);

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
    while (sizeof($deviations) > $deviationsCacheSize) {
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

    // *** Begin calculate Bollinger Bands
    $bbands = trader_bbands($deviations, $bbandsPeriod, 1, 1, TRADER_MA_TYPE_EMA);
    // *** End calculate Bollinger Bands

    // *** Begin create charts ***
    array_push(
        $slice->charts[CHART_CLOSE_PRICE_FIRST_PAIR]->series[0],
        new ChartPoint($dataRow->time->getTimestamp()*1000, $firstPair->close)
    );
    array_push(
        $slice->charts[CHART_CLOSE_PRICE_SECOND_PAIR]->series[0],
        new ChartPoint($dataRow->time->getTimestamp()*1000, $secondPair->close)
    );
    array_push(
        $slice->charts[CHART_DEVIATION]->series[CHART_DEVIATION_SERIE_DEVIATION],
        new ChartPoint($dataRow->time->getTimestamp()*1000, $deviation)
    );
    if (is_array($bbands)) {
        // Bollinger Bands
        $bbands[0] = array_values($bbands[0]);
        array_push(
            $slice->charts[CHART_DEVIATION]->series[CHART_DEVIATION_SERIE_DEVIATION_LOWER_BAND],
            new ChartPoint($dataRow->time->getTimestamp()*1000, $bbands[0][sizeof($bbands[0])-1])
        );
        $bbands[1] = array_values($bbands[1]);
        array_push(
            $slice->charts[CHART_DEVIATION]->series[CHART_DEVIATION_SERIE_DEVIATION_SMA],
            new ChartPoint($dataRow->time->getTimestamp()*1000, $bbands[1][sizeof($bbands[1])-1])
        );
        $bbands[2] = array_values($bbands[2]);
        array_push(
            $slice->charts[CHART_DEVIATION]->series[CHART_DEVIATION_SERIE_DEVIATION_UPPER_BAND],
            new ChartPoint($dataRow->time->getTimestamp()*1000, $bbands[2][sizeof($bbands[2])-1])
        );
    }
    array_push(
        $slice->charts[CHART_REAL_PAIRS_DIFF]->series[0],
        new ChartPoint($dataRow->time->getTimestamp()*1000, $firstPair->close-$secondPair->close)
    );
    array_push(
        $slice->charts[CHART_REAL_RATIO_DIVIDE_RATIO_SMA]->series[0],
        new ChartPoint($dataRow->time->getTimestamp()*1000, $ratio/$ratioSMA)
    );
    array_push(
        $slice->charts[CHART_SIGMA]->series[0],
        new ChartPoint($dataRow->time->getTimestamp()*1000, $sigma)
    );
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

// TODO: Добавить титл для среза.

file_put_contents("php://stdout", json_encode($slice));
