#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleAggregator;
use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleSource;
use CoinCorp\RateAnalyzer\ExchangeStateSlice;
use Commando\Command;
use Monolog\Logger;

ini_set('memory_limit', '-1');

define("CACHE_SIZE", 20);

define("INDICATOR_EMA9", "EMA9");
define("INDICATOR_MACD", "MACD");

$indicators = [INDICATOR_EMA9, INDICATOR_MACD];

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
$cmd->option('indicator')->describedAs('Convert price to indicator')->must(function($value) use ($indicators) {
    return in_array($value, $indicators);
});

/** @var \DateTime $from */
$from = $cmd['from'];
/** @var \DateTime $to */
$to = $cmd['to'];

$logger = new Logger('logger');
$aggregator = new CandleAggregator($logger);
$sources = require $cmd['sources'];

foreach ($sources as $source) {
    if ($source instanceof CandleSource) {
        if ($cmd['batch-size'] > 1) {
            $aggregator->addCandleEmitter(new CandleBatcher($source, (integer)$cmd['batch-size']));
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
    $exchangeStateSlice->seriesNames[$column] = $candle->label;
}

$exchangeStateSlice->series = array_fill(0, sizeof($dataRow->candles), []);

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
        switch ($cmd['indicator']) {
            case INDICATOR_EMA9:
                array_push($cache[$column], $candle->close);
                while (sizeof($cache[$column]) > CACHE_SIZE) {
                    array_shift($cache[$column]);
                }

                $value = 0;

                $EMA = trader_ema($cache[$column], 9);
                if (is_array($EMA)) {
                    $arr = array_values($EMA);
                    if (!empty($arr)) {
                        $value = $arr[sizeof($arr)-1];

                        array_push($exchangeStateSlice->series[$column], [
                            'start' => $candle->start->getTimestamp(),
                            'price' => $value,
                        ]);
                    }
                }

                break;
            case INDICATOR_MACD:
                break;
            default:
                array_push($exchangeStateSlice->series[$column], [
                    'start' => $candle->start->getTimestamp(),
                    'price' => $candle->close,
                ]);
                break;
        }
    }

    $finishTime = clone $dataRow->time;
}

if ($finishTime->getTimestamp() !== $to->getTimestamp()) {
    $logger->warn("Finish time before to", ['finishTime' => $finishTime, 'toTome' => $to]);
}

// Title
$exchangeStateSlice->title = "From " . $startTime->format('Y-m-d H:i:s e') . " to " . $finishTime->format('Y-m-d H:i:s e');

file_put_contents($cmd['output'], json_encode($exchangeStateSlice));
