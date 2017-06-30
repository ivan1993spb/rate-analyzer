#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleAggregator;
use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleSource;
use CoinCorp\RateAnalyzer\ExchangeStateSlice;
use Commando\Command;
use Monolog\Logger;

$cmd = new Command();
$cmd->setHelp('Generates JSON file with last candles');

$cmd->option('c')->aka('config')->describedAs('Config file')->required()->file();
$cmd->option('o')->aka('output')->describedAs('Output JSON file name')->required();
$cmd->option('n')->aka('candles-number')->describedAs('Candles count to JSON output')->required()->must(function($number) {
    return intval($number) > 0;
})->cast(function($number) {
    return intval($number);
});

$logger = new Logger('logger');
$aggregator = new CandleAggregator($logger, $cmd['candles-number']);
$config = require $cmd['config'];

foreach ($config['sources'] as $source) {
    if ($source instanceof CandleSource) {
        if ($config['candle-size'] > 1) {
            $aggregator->addCandleEmitter(new CandleBatcher($source, (integer)$config['candle-size']));
        } else {
            $aggregator->addCandleEmitter($source);
        }
    }
}

/** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
$dataRow = null;

foreach ($aggregator->rows() as $dataRow) {
}

$exchangeStateSlice = new ExchangeStateSlice();

// Добавляем названия графиков
foreach ($dataRow->candles as $column => $candle) {
    $exchangeStateSlice->seriesNames[$column] = $candle->label;
}

// Конец отчета
$finishTime = $dataRow->time;
/** @var \DateTime|null $startTime */
$startTime = null;

$exchangeStateSlice->series = array_fill(0, sizeof($dataRow->candles), []);

// Копируем тренд
for ($i = 0; $dataRow !== null; $i++) {
    foreach ($dataRow->candles as $column => $candle) {
        array_push($exchangeStateSlice->series[$column], [
            'start' => $candle->start->getTimestamp(),
            'price' => $candle->close,
        ]);
    }

    if ($dataRow->prev === null) {
        $startTime = $dataRow->time;
    } else {
        $dataRow = $dataRow->prev;
    }
}


// Title
$exchangeStateSlice->title = "From " . $startTime->format('Y-m-d H:i:s e') . " to " . $finishTime->format('Y-m-d H:i:s e');

file_put_contents($cmd['output'], json_encode($exchangeStateSlice));
