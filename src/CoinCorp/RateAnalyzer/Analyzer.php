<?php

namespace CoinCorp\RateAnalyzer;

use CoinCorp\RateAnalyzer\Exceptions\InvalidCapacityException;
use Monolog\Logger;

/**
 * Class Analyzer
 *
 * @package CoinCorp\RateAnalyzer
 */
class Analyzer
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

        ini_set("trader.real_precision", 10);
    }

    /**
     * @throws \CoinCorp\RateAnalyzer\Exceptions\InvalidCapacityException
     */
    public function analyze()
    {
        $mainColumn = 0;
        $cacheSize = 100;
        $cache = [];

        // Пременные
        // - Используемые индикаторы
        // - Параметры используемых индикаторов
        // - Насколько и за какой срок курс должен повыситься
        // - Минимальная и максимальная длина куска состояния рынка
        $minTrendLength = 10; // Свечей
        $trend = null;
        $trendLength = 0;

        $startPrice = 0;

        $cursor = 0;

        // Return if nothing to analyze
        if ($this->aggregator->capacity() === 0) {
            return;
        }

        foreach ($this->aggregator->rows() as $dataRow) {
            array_push($cache, $dataRow->candles[$mainColumn]->close);
            while (sizeof($cache) > $cacheSize) {
                array_shift($cache);
            }

            $macd = trader_macd($cache, 10, 21, 9);
            if (trader_errno() === TRADER_ERR_SUCCESS) {
                $macd = array_values($macd[0]);

                $this->log->info("MACD", [$macd[sizeof($macd)-1]]);

                if ($macd[sizeof($macd)-1] > 0) {
                    if ($trend != 'up') {
                        if ($trend === 'down' && $trendLength >= $minTrendLength) {
                            $finishPrice = $dataRow->candles[$mainColumn]->close;
                            $this->log->alert("DOWN -> UP", [$trendLength, $finishPrice / $startPrice]);
                            echo "DOWN -> UP", PHP_EOL;
                            $this->save($dataRow, $trendLength);
                        }

                        $trend = 'up';
                        $trendLength = 0;
                        $startPrice = $dataRow->candles[$mainColumn]->close;
                    }
                } elseif ($macd[sizeof($macd)-1] < 0) {
                    if ($trend != 'down') {
                        if ($trend === 'up' && $trendLength >= $minTrendLength) {
                            $finishPrice = $dataRow->candles[$mainColumn]->close;
                            $this->log->alert("UP -> DOWN", [$trendLength, $finishPrice / $startPrice]);
                            echo "UP -> DOWN", PHP_EOL;
                            $this->save($dataRow, $trendLength);
                        }

                        $trend = 'down';
                        $trendLength = 0;
                        $startPrice = $dataRow->candles[$mainColumn]->close;
                    }
                }

                $trendLength += 1;
            } else {
                $this->log->warn("Cannot calculate MACD");
            }
        }
    }

    private function save(DataRow $dataRow, $count)
    {
        $data = [];
        for ($i = 0; $i < $count && $dataRow !== null; $i++) {
            array_push($data, $dataRow->candles[0]->close);
            $dataRow = $dataRow->prev;
            fputcsv(STDOUT, [$i+1, $dataRow->candles[0]->start->getTimestamp(), $dataRow->candles[0]->close]);
        }
        echo "-------------------", PHP_EOL;
    }
}
