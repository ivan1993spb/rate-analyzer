#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleAggregator;
use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleSource;
use CoinCorp\RateAnalyzer\Chart;
use CoinCorp\RateAnalyzer\ChartPoint;
use CoinCorp\RateAnalyzer\ExchangeStateSlice;
use CoinCorp\Testing\Account;
use Commando\Command;
use Monolog\Logger;

ini_set("trader.real_precision", 10);

//
// Constants
//

define("DEFAULT_PERIOD_SMA_RATIO", 60*24*7);
define("DEFAULT_PERIOD_BANDS", 5);


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

// Состояния торгового бота
define("STATE_DEVIATION_PLUS", 0);
define("STATE_DEVIATION_MINUS", 1);

define("DEPOSIT_ACCOUNT_START_FIRST", 1.0);
define("DEPOSIT_ACCOUNT_START_SECOND", 1.0);

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
    return $value > 1 && $value <= 100000;
});
$cmd->option('period-bbands')->describedAs('Bollinger Bands period for deviation')->default(DEFAULT_PERIOD_BANDS)->must(function($value) {
    return $value > 1 && $value <= 100000;
});
$cmd->option('first-fee')->describedAs('First pair trade fee')->default(0.002)->cast(function($value) {
    return doubleval($value);
});
$cmd->option('second-fee')->describedAs('Second pair trade fee')->default(0.002)->cast(function($value) {
    return doubleval($value);
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
/** @var integer $bBandsPeriod */
$bBandsPeriod = intval($cmd['period-bbands']);
$deviationsCacheSize = $bBandsPeriod;
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

/** @var integer|null $state */
$state = null;

$firstAccount = new Account(DEPOSIT_ACCOUNT_START_FIRST, 0.0);
$secondAccount = new Account(DEPOSIT_ACCOUNT_START_SECOND, 0.0);

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
    /** @var float $deviationSMA2 */
    $deviationSMA2 = 0;
    if (sizeof($deviations) > 1) {
        $deviationSMA2 = array_sum(array_slice($deviations, -2))/2;
    }

    $logger->info("Stats", [
        'ratio'          => $ratio,
        'ratioSMA'       => $ratioSMA,
        'sigma'          => $sigma,
        'deviation'      => $deviation,
        'SMA2 deviation' => $deviationSMA2,
    ]);
    // *** End calculate sigma, deviation, SMA2(deviation) and SMA(ratio) ***

    // *** Begin check time range ***
    if ($dataRow->time->getTimestamp() < $from->getTimestamp()) {
        $generator->next();
        continue;
    }
    if ($dataRow->time->getTimestamp() > $to->getTimestamp()) {
        break;
    }
    // *** End check time range ***

    // Если данных о расхождении в кеше не достаточно - ждем
    if (sizeof($deviations) < $deviationsCacheSize) {
        $generator->next();
        continue;
    }

    // *** Begin calculate Bollinger Bands
    /** @var float[][]|false $bBands */
    $bBands = trader_bbands($deviations, $bBandsPeriod, -1, -1, TRADER_MA_TYPE_EMA);
    $deviationLowerBandValues = array_values($bBands[0]);
    $deviationUpperBandValues = array_values($bBands[2]);

    /** @var float $deviationLowerBand */
    $deviationLowerBand = $deviationLowerBandValues[sizeof($deviationLowerBandValues)-1];
    /** @var float $deviationUpperBand */
    $deviationUpperBand = $deviationUpperBandValues[sizeof($deviationUpperBandValues)-1];

    $logger->info("Bollinger Bands", [
        'deviationLowerBand' => $deviationLowerBand,
        'deviationUpperBand' => $deviationUpperBand,
    ]);
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
    array_push(
        $slice->charts[CHART_DEVIATION]->series[CHART_DEVIATION_SERIE_DEVIATION_LOWER_BAND],
        new ChartPoint($dataRow->time->getTimestamp()*1000, $deviationLowerBand)
    );
    array_push(
        $slice->charts[CHART_DEVIATION]->series[CHART_DEVIATION_SERIE_DEVIATION_SMA],
        new ChartPoint($dataRow->time->getTimestamp()*1000, $deviationSMA2)
    );
    array_push(
        $slice->charts[CHART_DEVIATION]->series[CHART_DEVIATION_SERIE_DEVIATION_UPPER_BAND],
        new ChartPoint($dataRow->time->getTimestamp()*1000, $deviationUpperBand)
    );
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

    // *** Begin draw lines ***
    if ($state !== STATE_DEVIATION_MINUS) {
        if ($deviation < 0 && $deviationSMA2 < 0 && $deviationLowerBand < 0 && $deviation < $deviationLowerBand &&
            $deviationSMA2 < $deviationLowerBand) {

            $logger->info("Change state", [
                'time'               => $dataRow->time,
                'state'              => STATE_DEVIATION_MINUS,
                'deviation'          => $deviation,
                'deviationSMA2'      => $deviationSMA2,
                'deviationLowerBand' => $deviationLowerBand,
            ]);

            $state = STATE_DEVIATION_MINUS;

            array_push($slice->verticalLines, [
                'color' => 'green',
                'value' => $dataRow->time->getTimestamp(),
            ]);

            $firstAccount->long($firstPair->close, $firstPairFee);
            $secondAccount->short($secondPair->close, $secondPairFee);
        }
    }
    if ($state !== STATE_DEVIATION_PLUS) {
        if ($deviation > 0 && $deviationSMA2 > 0 && $deviationUpperBand > 0 && $deviation > $deviationUpperBand &&
            $deviationSMA2 > $deviationUpperBand) {

            $logger->info("Change state", [
                'time'               => $dataRow->time,
                'state'              => STATE_DEVIATION_PLUS,
                'deviation'          => $deviation,
                'deviationSMA2'      => $deviationSMA2,
                'deviationUpperBand' => $deviationUpperBand,
            ]);

            $state = STATE_DEVIATION_PLUS;

            array_push($slice->verticalLines, [
                'color' => 'red',
                'value' => $dataRow->time->getTimestamp(),
            ]);

            $firstAccount->short($firstPair->close, $firstPairFee);
            $secondAccount->long($secondPair->close, $secondPairFee);
        }
    }
    // *** End draw lines ***

    // *** Begin finalize iteration ***
    $generator->next();
    if (!$generator->valid()) {
        $logger->info("Generator closed");
        break;
    }
    // *** End finalize iteration ***
}

//
// Log account stats
//

$logger->info("First account", [
    'currency' => $firstAccount->getCurrency(),
    'asset'    => $firstAccount->getAsset(),
    'fee'      => $firstAccount->getFee(),
    'trades'   => $firstAccount->getTrades(),
]);
$logger->info("Second account", [
    'currency' => $secondAccount->getCurrency(),
    'asset'    => $secondAccount->getAsset(),
    'fee'      => $secondAccount->getFee(),
    'trades'   => $secondAccount->getTrades(),
]);
$start = DEPOSIT_ACCOUNT_START_FIRST + DEPOSIT_ACCOUNT_START_SECOND;
$deposit = $firstAccount->getDeposit($firstPair->close) + $secondAccount->getDeposit($secondPair->close);
$logger->info("Summary", [
    'start'    => $start,
    'deposit'  => $deposit,
    '%'        => sprintf("%0.2f%%", (1-$start/$deposit)*100),
    'trades'   => $firstAccount->getTrades() + $secondAccount->getTrades(),
]);

//
// Print time slice
//

// TODO: Добавить титл для среза.

file_put_contents("php://stdout", json_encode($slice));
