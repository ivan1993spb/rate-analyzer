#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleEmitterInterface;
use CoinCorp\RateAnalyzer\Scanners\CandleEmitterScanner;
use Commando\Command;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

$cmd = new Command();
$cmd->option('s')->aka('sources')->describedAs('Config file with list of sources')->required()->file();
$cmd->option('j')->aka('json')->describedAs('Output json')->boolean();

/** @var \CoinCorp\RateAnalyzer\CandleEmitterInterface[] $sources */
$sources = require $cmd['sources'];

// TODO: Добавить опцию batch-size.

if ($cmd['json']) {
    $sources = [];
    $logger = new Logger('logger');
    $logger->pushHandler(new NullHandler());

    foreach ($sources as $emitter) {
        if ($emitter instanceof CandleEmitterInterface) {
            $scanner = new CandleEmitterScanner($emitter, $logger);

            $ranges = [];

            foreach ($scanner->scan() as $range) {
                array_push($ranges, [
                    'start'  => $range->start->getTimestamp(),
                    'finish' => $range->finish->getTimestamp(),
                ]);
            }

            array_push($sources, [
                'name'   => $emitter->getName(),
                'ranges' => $ranges,
            ]);
        }
    }

    echo json_encode($sources);
} else {
    $logger = new Logger('logger');

    foreach ($sources as $emitter) {
        if ($emitter instanceof CandleEmitterInterface) {
            $scanner = new CandleEmitterScanner($emitter, $logger);
            $generator = $scanner->scan();
            if ($generator->valid()) {
                $count = 0;
                foreach ($generator as $range) {
                    $count += 1;
                    $logger->info("Range", [
                        'start'    => $range->start->format('r'),
                        'finish'   => $range->finish->format('r'),
                        'duration' => sprintf("%dh", floor(($range->finish->getTimestamp() - $range->start->getTimestamp()) / 3600)),
                    ]);
                }
                $logger->info("Range number", ['count' => $count]);
            } else {
                $logger->info("Ranges not found");
            }
        }
    }
}
