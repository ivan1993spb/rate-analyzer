#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleEmitterInterface;
use CoinCorp\RateAnalyzer\Scanners\CandleEmitterScanner;
use Commando\Command;
use Monolog\Logger;

$cmd = new Command();
$cmd->option('s')->aka('sources')->describedAs('Config file with list of sources')->required()->file();
$cmd->option('j')->aka('json')->describedAs('Filename for output as JSON')->boolean();
$cmd->option('m')->aka('md')->describedAs('Filename output as markdown')->boolean();
$cmd->option('b')->aka('batch-size')->describedAs('Candles number to batch (Each candle equal one minute by default)')->default(1)->must(function($value) {
    return is_numeric($value) && $value > 0;
});

/** @var \CoinCorp\RateAnalyzer\CandleEmitterInterface[] $sources */
$sources = require $cmd['sources'];

if ($cmd['json']) {
    $output = [];
    $logger = new Logger('logger');

    foreach ($sources as $emitter) {
        if ($emitter instanceof CandleEmitterInterface) {
            if ($cmd['batch-size'] > 1) {
                $emitter = new CandleBatcher($emitter, (integer)$cmd['batch-size']);
            }

            $scanner = new CandleEmitterScanner($emitter, $logger);

            $ranges = [];

            foreach ($scanner->scan() as $range) {
                array_push($ranges, [
                    'start'  => $range->start->getTimestamp(),
                    'finish' => $range->finish->getTimestamp(),
                ]);
            }

            array_push($output, [
                'name'   => $emitter->getName(),
                'ranges' => $ranges,
            ]);
        } else {
            $logger->warn("Invalid source type");
        }
    }

    echo json_encode($output);
} elseif ($cmd['md']) {
    $logger = new Logger('logger');

    echo "\n";
    echo "# Candle ranges\n";
    echo "\n";

    $sourceCount = 0;

    foreach ($sources as $emitter) {
        if ($emitter instanceof CandleEmitterInterface) {
            if ($cmd['batch-size'] > 1) {
                $emitter = new CandleBatcher($emitter, (integer)$cmd['batch-size']);
            }

            $sourceCount++;

            $scanner = new CandleEmitterScanner($emitter, $logger);

            $ranges = [];
            echo "## ", $emitter->getName(), "\n";
            echo "\n";
            printf("Candle size = %d seconds\n", $emitter->getCandleSize());
            echo "\n";

            $generator = $scanner->scan();
            $duration = 0;
            if ($generator->valid()) {
                $count = 0;
                foreach ($generator as $range) {
                    $count++;
                    $rangeDuration = $range->finish->getTimestamp() - $range->start->getTimestamp();
                    $duration += $rangeDuration;
                    printf("* Range **%dh**: from `%s` to `%s`\n", floor($rangeDuration / 3600),
                        $range->start->format('r'), $range->finish->format('r'));
                }
                echo "\n";
                printf("**%d** ranges, duration **%dh**\n", $count, floor($duration / 3600));
            } else {
                echo "No ranges\n";
            }
            echo "\n";
        } else {
            $logger->warn("Invalid source type");
        }
    }

    echo "---\n";
    echo "**", $sourceCount, " sources**\n";
} else {
    $logger = new Logger('logger');

    foreach ($sources as $emitter) {
        if ($emitter instanceof CandleEmitterInterface) {
            if ($cmd['batch-size'] > 1) {
                $emitter = new CandleBatcher($emitter, (integer)$cmd['batch-size']);
            }

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
        } else {
            $logger->warn("Invalid source type");
        }
    }
}
