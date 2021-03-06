#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleEmitterInterface;
use Commando\Command;
use Monolog\Logger;

ini_set("trader.real_precision", 10);
ini_set('memory_limit', '-1');

define("DEFAULT_BATCH_SIZE", 1);

// Indicator periods

define("PERIOD_ADX_SHORT", 4);
define("PERIOD_ADX_LONG", 12);

define("PERIOD_ATR_SHORT", 4);
define("PERIOD_ATR_LONG", 12);

define("PERIOD_SMA_SHORT", 4);
define("PERIOD_SMA_MEDIUM", 9);
define("PERIOD_SMA_LONG", 18);

define("PERIOD_RSI_LONG", 14);

define("CANDLE_CACHE_SIZE", 30);

/**
 * @param array $array
 * @return mixed
 */
function lastOrFalse($array = []) {
    if (is_array($array)) {
        if (!empty($array)) {
            $array = array_values($array);
            return $array[count($array)-1];
        }
        return false;
    }
    return $array;
}

$cmd = new Command();
$cmd->setHelp('Calculates and sorts pairs indicators and stats');

$cmd->option('s')->aka('sources')->describedAs('Config file with list of sources')->required()->file();
$cmd->option('b')->aka('batch-size')->describedAs('Candles number to batch')->default(DEFAULT_BATCH_SIZE)->must(function($value) {
    return is_numeric($value) && $value > 0;
});
$cmd->option('from')->describedAs('Time UTC')->default(new DateTime("0001-01-01", new DateTimeZone('UTC')))->cast(function($value) {
    return new DateTime($value, new DateTimeZone('UTC'));
});
$cmd->option('to')->describedAs('Time UTC')->default(new DateTime('now', new DateTimeZone('UTC')))->cast(function($value) {
    return new DateTime($value, new DateTimeZone('UTC'));
});
$cmd->option('up')->describedAs('Print only growing up pairs')->default(false)->boolean();

/** @var \DateTime $from */
$from = $cmd['from'];
/** @var \DateTime $to */
$to = $cmd['to'];
/** @var integer $batchSize */
$batchSize = intval($cmd['batch-size']);
/** @var \CoinCorp\RateAnalyzer\CandleEmitterInterface[] $sources */
$sources = require $cmd['sources'];
/** @var boolean $flagPrintOnlyGrowingUpPairs */
$flagPrintOnlyGrowingUpPairs = $cmd['up'];

$logger = new Logger('logger');

echo "\n";
echo "# Last pairs candles stats\n";
echo "\n";

if ($flagPrintOnlyGrowingUpPairs) {
    $logger->info("Filtering pairs that don't growing up is enabled");
    echo "Only growing up pairs\n";
    echo "\n";
}

foreach ($sources as $emitter) {
    if ($emitter instanceof CandleEmitterInterface) {
        if ($batchSize > 1) {
            $emitter = new CandleBatcher($emitter, $batchSize);
        }

        /** @var \CoinCorp\RateAnalyzer\Candle[] $candles */
        $candles = [];

        $ADXShort  = [];
        $ADXLong   = [];
        $ATRShort  = [];
        $ATRLong   = [];
        $SMAShort  = [];
        $SMAMedium = [];
        $SMALong   = [];
        $RSILong   = [];

        foreach ($emitter->candles() as $candle) {
            array_push($candles, $candle);
            while (sizeof($candles) > CANDLE_CACHE_SIZE) {
                array_shift($candles);
            }

            $high  = [];
            $low   = [];
            $close = [];

            foreach ($candles as $candle_) {
                array_push($high, $candle_->high);
                array_push($low, $candle_->low);
                array_push($close, $candle_->close);
            }

            $ADXShort  = trader_adx($high, $low, $close, PERIOD_ADX_SHORT);
            $ADXLong   = trader_adx($high, $low, $close, PERIOD_ADX_LONG);
            $ATRShort  = trader_atr($high, $low, $close, PERIOD_ATR_SHORT);
            $ATRLong   = trader_atr($high, $low, $close, PERIOD_ATR_LONG);
            $SMAShort  = trader_sma($close, PERIOD_SMA_SHORT);
            $SMAMedium = trader_sma($close, PERIOD_SMA_MEDIUM);
            $SMALong   = trader_sma($close, PERIOD_SMA_LONG);
            $RSILong   = trader_rsi($close, PERIOD_RSI_LONG);
        }

        $candle = lastOrFalse($candles);
        if ($candle) {
            $ADXShortValue = lastOrFalse($ADXShort);
            if ($ADXShortValue === false) {
                $logger->warn("Cannot get ADXShort value", ['emitter' => $emitter->getName()]);
                continue;
            }
            $ADXLongValue = lastOrFalse($ADXLong);
            if ($ADXLongValue === false) {
                $logger->warn("Cannot get ADXLong value", ['emitter' => $emitter->getName()]);
                continue;
            }
            $ATRShortValue = lastOrFalse($ATRShort);
            if ($ATRShortValue === false) {
                $logger->warn("Cannot get ATRShort value", ['emitter' => $emitter->getName()]);
                continue;
            }
            $ATRLongValue = lastOrFalse($ATRLong);
            if ($ATRLongValue === false) {
                $logger->warn("Cannot get ATRLong value", ['emitter' => $emitter->getName()]);
                continue;
            }
            $SMAShortValue = lastOrFalse($SMAShort);
            if ($SMAShortValue === false) {
                $logger->warn("Cannot get SMAShort value", ['emitter' => $emitter->getName()]);
                continue;
            }
            $SMAMediumValue = lastOrFalse($SMAMedium);
            if ($SMAMediumValue === false) {
                $logger->warn("Cannot get SMAMedium value", ['emitter' => $emitter->getName()]);
                continue;
            }
            $SMALongValue = lastOrFalse($SMALong);
            if ($SMALongValue === false) {
                $logger->warn("Cannot get SMALong value", ['emitter' => $emitter->getName()]);
                continue;
            }
            $RSILongValue = lastOrFalse($RSILong);
            if ($RSILongValue === false) {
                $logger->warn("Cannot get RSILong value", ['emitter' => $emitter->getName()]);
                continue;
            }

            // Filter pairs
            if ($flagPrintOnlyGrowingUpPairs) {
                // Если пара не растет, игнорируем
                if (!($SMAShortValue > $SMAMediumValue && $SMAMediumValue > $SMALongValue)) {
                    $logger->info("Filter pair", ['emitter' => $emitter->getName()]);
                    continue;
                }
            }

            $logger->info("Create pair", ['emitter' => $emitter->getName()]);

            echo "## ", $emitter->getName(), "\n";
            echo "\n";
            echo "Candle size = ", $emitter->getCandleSize(), " seconds\n";
            echo "\n";

            printf("Last candle: date `%s`, close = `%f`, volume = `%f`, trades = `%d`\n", $candle->start->format('r'), $candle->close,
                $candle->volume, $candle->trades);

            echo PHP_EOL;

            echo "```\n";
            printf("ADXShort(%d) = ", PERIOD_ADX_SHORT);
            var_dump($ADXShortValue);
            printf("ADXLong(%d) = ", PERIOD_ADX_LONG);
            var_dump($ADXLongValue);
            printf("ATRShort(%d) = ", PERIOD_ATR_SHORT);
            var_dump($ATRShortValue);
            printf("ATRLong(%d) = ", PERIOD_ATR_LONG);
            var_dump($ATRLongValue);
            echo PHP_EOL;

            printf("SMAShort(%d) = ", PERIOD_SMA_SHORT);
            var_dump($SMAShortValue);
            printf("SMAMedium(%d) = ", PERIOD_SMA_MEDIUM);
            var_dump($SMAMediumValue);
            printf("SMALong(%d) = ", PERIOD_SMA_LONG);
            var_dump($SMALongValue);
            echo PHP_EOL;

            printf("RSILong(%d) = ", PERIOD_RSI_LONG);
            var_dump($RSILongValue);
            echo "```\n";

            echo PHP_EOL;

            echo "Volatility:", PHP_EOL;
            echo PHP_EOL;

            echo "```\n";
            printf("ATRShort(%d)/SMAShort(%d) = %0.2f%%\n", PERIOD_ATR_SHORT, PERIOD_SMA_SHORT, $ATRShortValue/$SMAShortValue*100);
            printf("ATRLong(%d)/SMALong(%d) = %0.2f%%\n", PERIOD_ATR_LONG, PERIOD_SMA_LONG, $ATRLongValue/$SMALongValue*100);
            echo "```\n";

        } else {
            $logger->warn("Cannot gen last candle", ['emitter' => $emitter->getName()]);
        }

        echo PHP_EOL;
    } else {
        $logger->warn("Invalid source type");
    }
}
