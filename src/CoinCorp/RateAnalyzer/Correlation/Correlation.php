<?php

namespace CoinCorp\RateAnalyzer\Correlation;

use CoinCorp\RateAnalyzer\AggregatorInterface;
use CoinCorp\RateAnalyzer\Candle;
use Monolog\Logger;

/**
 * Class Correlation
 *
 * @package CoinCorp\RateAnalyzer
 */
class Correlation
{
    /**
     * @var \CoinCorp\RateAnalyzer\AggregatorInterface
     */
    private $aggregator;

    /**
     * @var \Monolog\Logger
     */
    private $log;

    /**
     * Analyzer constructor.
     *
     * @param \CoinCorp\RateAnalyzer\AggregatorInterface $aggregator
     * @param \Monolog\Logger                            $log
     */
    public function __construct(AggregatorInterface $aggregator, Logger $log)
    {
        $this->aggregator = $aggregator;
        $this->log = $log;
    }

    public function findCorrelation()
    {
        $variablePerCandle = 4;

        /** @var \CoinCorp\RateAnalyzer\Correlation\CandleVariable[] $variables */
        $variables = [
            // Column 0
            new CandleVariable('1_close', function(Candle $candle) {
                return $candle->close;
            }),
            new CandleVariable('1_vwp', function(Candle $candle) {
                return $candle->vwp;
            }),
            new CandleVariable('1_volume', function(Candle $candle) {
                return $candle->volume;
            }),
            new CandleVariable('1_trades', function(Candle $candle) {
                return $candle->trades;
            }),

            // Column 1
            new CandleVariable('2_close', function(Candle $candle) {
                return $candle->close;
            }),
            new CandleVariable('2_vwp', function(Candle $candle) {
                return $candle->vwp;
            }),
            new CandleVariable('2_volume', function(Candle $candle) {
                return $candle->volume;
            }),
            new CandleVariable('2_trades', function(Candle $candle) {
                return $candle->trades;
            }),
        ];

        // Return if nothing to analyze
        if ($this->aggregator->capacity() === 0) {
            return;
        }

        /** @var float[] $means */
        $means = array_fill(0, sizeof($variables), 0.0);

        // Вычисление средних значений
        foreach ($this->aggregator->rows() as $index => $dataRow) {
            $count = $index + 1;
            $k = ($count - 1)  / $count;

            // Для каждой свечи
            foreach ($dataRow->candles as $column => $candle) {
                // Для каждой переменной для свечи
                for ($i = 0; $i < $variablePerCandle; $i++) {
                    $variable_index = $variablePerCandle*$column + $i;
                    $variable = $variables[$variable_index];
                    $variable->update($candle);
                    $means[$variable_index] = $means[$variable_index] * $k  + $variable->value / $count;
                }
            }
        }

        /** @var float[][] $covsXYZ */
        $covsXYZ = array_fill(0, sizeof($variables), array_fill(0, sizeof($variables), 0.0));

        /** @var float[] $varS */
        $varS = array_fill(0, sizeof($variables), 0.0);

        foreach ($this->aggregator->rows() as $index => $dataRow) {
            foreach ($dataRow->candles as $column => $candle) {
                // Для каждой переменной для свечи
                for ($i = 0; $i < $variablePerCandle; $i++) {
                    $variable_index = $variablePerCandle*$column + $i;
                    $variable = $variables[$variable_index];
                    $variable->update($candle);
                }
            }

            foreach ($variables as $xi => $variableX) {
                foreach ($variables as $yi => $variableY) {
                    if ($xi !== $yi) {
                        $covsXYZ[$xi][$yi] += ($variableX->value - $means[$xi]) * ($variableY->value - $means[$yi]);
                    }
                }
            }

            foreach ($variables as $i => $variable) {
                $varS[$i] += pow($variable->value - $means[$i], 2);
            }
        }

        $varS = array_map('sqrt', $varS);

        $this->log->info("means", $means);
        $this->log->info("varS", $varS);

        $R_XYZ = array_fill(0, sizeof($variables), array_fill(0, sizeof($variables), 0.0));

        foreach ($variables as $xi => $variableX) {
            foreach ($variables as $yi => $variableY) {
                if ($xi !== $yi) {
                    $covXY = $covsXYZ[$xi][$yi];
                    $sX = $varS[$xi];
                    $sY = $varS[$yi];
                    $R_XYZ[$xi][$yi] = $covXY / ($sX * $sY);
                }
            }
        }

//        var_dump("means:", $means, "covsXYZ", $covsXYZ, "varS", $varS, "r", $R_XYZ);

        $this->log->info("R_XYZ", $R_XYZ);

        echo PHP_EOL;
        printf("%10s ", ' ');
        foreach ($variables as $xi => $variableX) {
            printf("%10s ", $variableX->name);
        }
        echo PHP_EOL;
        foreach ($variables as $xi => $variableX) {
            printf("%10s ", $variableX->name);
            foreach ($variables as $yi => $variableY) {
                if ($xi !== $yi) {
                    printf("%10f ", $R_XYZ[$xi][$yi]);
//                    var_dump($R_XYZ[$xi][$yi]);
                } else {
                    printf("%10s ", ' ');
                }
            }
            echo PHP_EOL;
        }

    }
}
