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
ini_set('memory_limit', '-1');

define("DEFAULT_MA_PERIOD", 10);

define("CHART_SERIE_PRICE", 0);
define("CHART_SERIE_MA", 1);

$cmd = new Command();
$cmd->setHelp('Generates JSON file with last candles');

$cmd->option('s')->aka('sources')->describedAs('Config file with list of sources')->required()->file();
$cmd->option('o')->aka('output')->describedAs('Output JSON file name')->default('php://stdout');
$cmd->option('b')->aka('batch-size')->describedAs('Candles number to batch')->default(1)->must(function($value) {
    return is_numeric($value) && $value > 0;
});
$cmd->option('from')->describedAs('Time UTC')->default(new DateTime("0001-01-01", new DateTimeZone('UTC')))->cast(function($value) {
    return new DateTime($value, new DateTimeZone('UTC'));
});
$cmd->option('to')->describedAs('Time UTC')->default(new DateTime('now', new DateTimeZone('UTC')))->cast(function($value) {
    return new DateTime($value, new DateTimeZone('UTC'));
});
$cmd->option('ma')->describedAs('Moving average period')->default(DEFAULT_MA_PERIOD)->must(function($value) {
    return $value > 1 && $value <= 100000;
});

/** @var \DateTime $from */
$from = $cmd['from'];
/** @var \DateTime $to */
$to = $cmd['to'];
/** @var integer $periodMA */
$periodMA = intval($cmd['ma']);
$cacheSize = $periodMA;
/** @var integer $batchSize */
$batchSize = intval($cmd['batch-size']);

$logger = new Logger('logger');
$aggregator = new CandleAggregator($logger);
$sources = require $cmd['sources'];

foreach ($sources as $source) {
    if ($source instanceof CandleSource) {
        if ($batchSize > 1) {
            $aggregator->addCandleEmitter(new CandleBatcher($source, $batchSize));
        } else {
            $aggregator->addCandleEmitter($source);
        }
    }
}

// Составляем отчет
$exchangeStateSlice = new ExchangeStateSlice();

$generator = $aggregator->rows();
if (!$generator->valid()) {
    exit;
}

/** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
$dataRow = $generator->current();

// Добавляем названия графиков
foreach ($dataRow->candles as $column => $candle) {
    $exchangeStateSlice->charts[$column] = new Chart(sprintf("%s prices and MA(%d)", $candle->label, $periodMA), [
        CHART_SERIE_PRICE => sprintf("%s price", $candle->label),
        CHART_SERIE_MA    => sprintf("%s MA%d", $candle->label, $periodMA),
    ]);
}

/** @var \DateTime|null $startTime */
$startTime = null;

/** @var \DateTime|null $finishTime */
$finishTime = null;

$cache = array_fill(0, sizeof($dataRow->candles), []);

// Копируем тренд
foreach ($generator as $dataRow) {
    if ($dataRow->time->getTimestamp() < $from->getTimestamp()) {
        continue;
    }
    if ($dataRow->time->getTimestamp() > $to->getTimestamp()) {
        break;
    }

    if ($startTime === null) {
        $startTime = clone $dataRow->time;
        if ($startTime->getTimestamp() !== $from->getTimestamp()) {
            $logger->warn("Start time after from", ['startTime' => $startTime, 'fromTime' => $from]);
        }
    }

    foreach ($dataRow->candles as $column => $candle) {
        array_push($cache[$column], $candle->close);
        while (sizeof($cache[$column]) > $cacheSize) {
            array_shift($cache[$column]);
        }

        $value = null;

        $SMA = trader_sma($cache[$column], $periodMA);
        if (is_array($SMA)) {
            $arr = array_values($SMA);
            if (!empty($arr)) {
                $value = $arr[sizeof($arr)-1];
            }
        }
        array_push(
            $exchangeStateSlice->charts[$column]->series[CHART_SERIE_PRICE],
            new ChartPoint($candle->start->getTimestamp()*1000, $candle->close)
        );

        if ($value !== null) {
            array_push(
                $exchangeStateSlice->charts[$column]->series[CHART_SERIE_MA],
                new ChartPoint($candle->start->getTimestamp()*1000, $value)
            );
        }
    }

    $finishTime = clone $dataRow->time;
}

if ($finishTime->getTimestamp() !== $to->getTimestamp()) {
    $logger->warn("Finish time before to", ['finishTime' => $finishTime, 'toTome' => $to]);
}

// Title
$exchangeStateSlice->title = "Range from " . $startTime->format('Y-m-d H:i:s e') . " to " . $finishTime->format('Y-m-d H:i:s e');

file_put_contents($cmd['output'], json_encode($exchangeStateSlice));
