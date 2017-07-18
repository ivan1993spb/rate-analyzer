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



$cmd = new Command();

$cmd->option('s')->aka('sources')->describedAs('Config file with list of sources')->required()->file();
$cmd->option('b')->aka('batch-size')->describedAs('Candles number to batch')->default(1)->must(function($value) {
    return is_numeric($value) && $value > 0;
});
$cmd->option('from')->describedAs('Time UTC')->default(new DateTime("0001-01-01", new DateTimeZone('UTC')))->cast(function($value) {
    return new DateTime($value, new DateTimeZone('UTC'));
});
$cmd->option('to')->describedAs('Time UTC')->default(new DateTime('now', new DateTimeZone('UTC')))->cast(function($value) {
    return new DateTime($value, new DateTimeZone('UTC'));
});


/** @var \DateTime $from */
$from = $cmd['from'];
/** @var \DateTime $to */
$to = $cmd['to'];




$logger = new Logger('logger');
$aggregator = new CandleAggregator($logger);
/** @var \CoinCorp\RateAnalyzer\CandleEmitterInterface[] $sources */
$sources = require $cmd['sources'];

if (sizeof($sources) < 2) {
    $logger->error("Invalid source count < 2", ['count' => sizeof($sources)]);
    exit(1);
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *                                ДОБАВЛЕНИЕ ИСТОЧНИКОВ ДЛЯ АГГРЕГАЦИИ                                 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

foreach ($sources as $source) {
    if ($source instanceof CandleSource) {
        if ($cmd['batch-size'] > 1) {
            $aggregator->addCandleEmitter(new CandleBatcher($source, (integer)$cmd['batch-size']));
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
    exit;
}

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
 *                                          ПОДГОТОВКА                                                 *
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

define("PAIR_FIRST", 0);
define("PAIR_SECOND", 1);

define("PERIOD_SMA_PRICE_SHORT", 4);
define("PERIOD_SMA_PRICE_MEDIUM", 9);
define("PERIOD_SMA_PRICE_LONG", 18);

define("PERIOD_SMA_RATIO", 20);

define("PRICE_TAIL_CACHE_SIZE", max(PERIOD_SMA_PRICE_SHORT, PERIOD_SMA_PRICE_MEDIUM, PERIOD_SMA_PRICE_LONG, PERIOD_SMA_RATIO)-1);
define("PRICE_CACHE_SIZE", max(PERIOD_SMA_PRICE_SHORT, PERIOD_SMA_PRICE_MEDIUM, PERIOD_SMA_PRICE_LONG, PERIOD_SMA_RATIO));


define("PERIOD_SMA_DEVIATION_PLUS", 18);
define("PERIOD_SMA_DEVIATION_MINUS", 18);
define("PERIOD_ADX_DEVIATION", 3);
define("DEVIATION_CACHE_SIZE", max(PERIOD_SMA_DEVIATION_PLUS, PERIOD_SMA_DEVIATION_MINUS, PERIOD_ADX_DEVIATION*2));



$tailFirstPriceClose = [];
$tailSecondPriceClose = [];
$tailRatioPrices = [];

for ($i = 0; $i < PRICE_TAIL_CACHE_SIZE; $i++) {
    /** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
    $dataRow = $generator->current();

    $firstPair = $dataRow->candles[PAIR_FIRST];
    array_push($tailFirstPriceClose, $firstPair->close);

    $secondPair = $dataRow->candles[PAIR_SECOND];
    array_push($tailSecondPriceClose, $secondPair->close);

    // Ratio prices
    array_push($tailRatioPrices, $firstPair->close/$secondPair->close);

    $generator->next();
    if (!$generator->valid()) {
        break;
    }
}

$generator = $aggregator->rows();
if (!$generator->valid()) {
    $logger->warn("Empty aggregator");
    exit;
}





$dataRow = $generator->current();
$firstPair = $dataRow->candles[PAIR_FIRST];
$secondPair = $dataRow->candles[PAIR_SECOND];


$slice = new ExchangeStateSlice();
$slice->seriesNames = ["Deviation", "Deviation DI+", "Deviation DI-",
    "Common deposit BTC", "Price ".$firstPair->label, "Price ".$secondPair->label];
$slice->series = array_fill(0, sizeof($slice->seriesNames), []);



$firstPriceClose = [];
$secondPriceClose = [];
$ratioPrices = [];
$deviations = [];
$state = 0;



///////////////////// Emulation
$firstBTC = 1.0;
$depositFirst = 0.0;
$positionFirst = null;

$secondBTC = 1.0;
$depositSecond = 0.0;
$positionSecond = null;

$dataRow = $generator->current();
$firstPair = $dataRow->candles[PAIR_FIRST];
$secondPair = $dataRow->candles[PAIR_SECOND];
$startBTC = $firstBTC + $secondBTC + $depositFirst*$firstPair->close + $depositSecond*$secondPair->close;
//////////////////////////////


$green = 0;
$red = 0;

$fee = 0.002;
$feeResult = 0.0;



while (true) {
    /** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
    $dataRow = $generator->current();

    $firstPair = $dataRow->candles[PAIR_FIRST];
    array_push($firstPriceClose, $firstPair->close);
    while (sizeof($firstPriceClose) > PRICE_CACHE_SIZE) {
        array_push($tailFirstPriceClose, array_shift($firstPriceClose));
        array_shift($tailFirstPriceClose);
    }

    $secondPair = $dataRow->candles[PAIR_SECOND];
    array_push($secondPriceClose, $secondPair->close);
    while (sizeof($secondPriceClose) > PRICE_CACHE_SIZE) {
        array_push($tailSecondPriceClose, array_shift($secondPriceClose));
        array_shift($tailSecondPriceClose);
    }

    // Ratio prices
    array_push($ratioPrices, $firstPair->close/$secondPair->close);
    while (sizeof($ratioPrices) > PRICE_CACHE_SIZE) {
        array_push($tailRatioPrices, array_shift($ratioPrices));
        array_shift($tailRatioPrices);
    }

    // -- Begin calculate deviation --

    $ratioPricesSMA = array_values(trader_sma(array_merge($tailRatioPrices, $ratioPrices), PERIOD_SMA_RATIO));

    /** @var float $sum */
    $sum = 0;
    for ($i = 0; $i < sizeof($ratioPrices); $i++) {
        $sum += pow($ratioPrices[$i] - $ratioPricesSMA[$i], 2);
    }
    $sigma = sqrt($sum/sizeof($ratioPricesSMA));
    $deviation = ($ratioPrices[sizeof($ratioPrices)-1]-$ratioPricesSMA[sizeof($ratioPricesSMA)-1]) / $sigma;
    array_push($deviations, $deviation);
    while (sizeof($deviations) > DEVIATION_CACHE_SIZE) {
        array_shift($deviations);
    }

    // -- End calculate deviation --


    // * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
    // * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
    // * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *

    if (sizeof($deviations) === DEVIATION_CACHE_SIZE) {
        if ($dataRow->time->getTimestamp() < $from->getTimestamp()) {
            goto next;
        }
        if ($dataRow->time->getTimestamp() > $to->getTimestamp()) {
            break;
        }


        $deviationsMinus = array_map(function ($value) {
            return $value > 0 ? 0 : $value;
        }, $deviations);
        $deviationsPlus = array_map(function ($value) {
            return $value < 0 ? 0 : $value;
        }, $deviations);

        // -- Begin calculate indicators --

        $deviationPlusDI = array_slice(array_values(trader_plus_di($deviations, $deviations, $deviations, PERIOD_ADX_DEVIATION)), -1)[0];
        $deviationMinusDI = array_slice(array_values(trader_minus_di($deviations, $deviations, $deviations, PERIOD_ADX_DEVIATION)), -1)[0];
        $deviationSMA4 = array_slice(array_values(trader_sma($deviations, 4)), -1)[0];
        $deviationSMA9 = array_slice(array_values(trader_sma($deviations, 9)), -1)[0];
//        $deviationMinusSMA = array_slice(array_values(trader_sma($deviationsMinus, 3)), -1)[0];
//        $deviationPlusSMA = array_slice(array_values(trader_sma($deviationsPlus, PERIOD_SMA_DEVIATION_PLUS)), -1)[0];
        $firstPriceSMAShort = array_slice(array_values(trader_sma($firstPriceClose, PERIOD_SMA_PRICE_SHORT)), -1)[0];
        $firstPriceSMAMedium = array_slice(array_values(trader_sma($firstPriceClose, PERIOD_SMA_PRICE_MEDIUM)), -1)[0];
        $firstPriceSMALong = array_slice(array_values(trader_sma($firstPriceClose, PERIOD_SMA_PRICE_LONG)), -1)[0];
        $secondPriceSMAShort = array_slice(array_values(trader_sma($secondPriceClose, PERIOD_SMA_PRICE_SHORT)), -1)[0];
        $secondPriceSMAMedium = array_slice(array_values(trader_sma($secondPriceClose, PERIOD_SMA_PRICE_MEDIUM)), -1)[0];
        $secondPriceSMALong = array_slice(array_values(trader_sma($secondPriceClose, PERIOD_SMA_PRICE_LONG)), -1)[0];

        // -- End calculate indicators --


        if ($deviation < $deviationSMA4 && $deviationSMA4 < $deviationSMA9 && $deviationSMA9 < 0 && $state != 1
            && $deviationMinusDI > 65 && $deviationPlusDI < 35) {
            $state = 1;
            array_push($slice->verticalLines, [
                'color' => 'green',
                'value' => $dataRow->time->getTimestamp(),
            ]);
            $green += 1;
            if ($positionFirst != 'long') {
                $positionFirst = 'long';
                $feeResult += $firstBTC / $firstPair->close * $fee;

                $depositFirst += $firstBTC / $firstPair->close;
                $firstBTC = 0;
            }
            if ($positionSecond != 'short') {
                $positionSecond = 'short';
                $feeResult += $depositSecond * $secondPair->close * $fee;

                $secondBTC += $depositSecond * $secondPair->close;
                $depositSecond = 0;
            }
        }

        if ($deviation > $deviationSMA4 && $deviationSMA4 > $deviationSMA9 && $deviationSMA9 > 0 && $state != 2
            && $deviationPlusDI > 65 && $deviationMinusDI < 35) {
            $state = 2;
            array_push($slice->verticalLines, [
                'color' => 'red',
                'value' => $dataRow->time->getTimestamp(),
            ]);
            $red += 1;
            if ($positionFirst != 'short') {
                $positionFirst = 'short';

                $feeResult += $depositFirst * $firstPair->close * $fee;

                $firstBTC += $depositFirst * $firstPair->close;
                $depositFirst = 0;
            }
            if ($positionSecond != 'long') {
                $positionSecond = 'long';

                $feeResult += $secondBTC / $secondPair->close * $fee;


                $depositSecond += $secondBTC / $secondPair->close;
                $secondBTC = 0;
            }
        }




        array_push($slice->series[0], [
            'start' => $dataRow->time->getTimestamp(),
            'price' => $deviation,
        ]);
        array_push($slice->series[1], [
            'start' => $dataRow->time->getTimestamp(),
            'price' => $deviationPlusDI,
        ]);
        array_push($slice->series[2], [
            'start' => $dataRow->time->getTimestamp(),
            'price' => $deviationMinusDI,
        ]);

        $deposit = $firstBTC + $depositFirst*$firstPair->close + $secondBTC + $depositSecond*$secondPair->close;

        array_push($slice->series[3], [
            'start' => $dataRow->time->getTimestamp(),
            'price' => $deposit,
        ]);
        array_push($slice->series[4], [
            'start' => $dataRow->time->getTimestamp(),
            'price' => $firstPair->close,
        ]);
        array_push($slice->series[5], [
            'start' => $dataRow->time->getTimestamp(),
            'price' => $secondPair->close,
        ]);
    }

    // * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
    // * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
    // * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *

    next:

    $generator->next();
    if (!$generator->valid()) {
        break;
    }
}


/** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
//$dataRow = $generator->current();

$firstPair = $dataRow->candles[PAIR_FIRST];
$secondPair = $dataRow->candles[PAIR_SECOND];

$result = $firstBTC + $depositFirst*$firstPair->close + $secondBTC + $depositSecond*$secondPair->close;

$logger->info("END", [
    'result' => $result,
    'start'  => $startBTC,
    'proc'   => sprintf("%d%%", round($result/$startBTC*100)),
    'green'  => $green,
    'red'    => $red,
    'fee'    => $feeResult,
]);

file_put_contents("php://stdout", json_encode($slice));
