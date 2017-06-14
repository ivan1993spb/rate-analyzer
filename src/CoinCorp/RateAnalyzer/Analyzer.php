<?php

namespace CoinCorp\RateAnalyzer;

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
        $trendStart = null;

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
                            if ($finishPrice / $startPrice < 0.97) {
//                                echo "DOWN -> UP", PHP_EOL;
//                                $this->save($dataRow, $trendLength);
                                $this->toExchangeStateSlice($dataRow, $trendLength, [$trendStart]);
                            }
                        }

                        $trend = 'up';
                        $trendLength = 0;
                        $startPrice = $dataRow->candles[$mainColumn]->close;
                        $trendStart = $dataRow->candles[$mainColumn]->start;
                    }
                } elseif ($macd[sizeof($macd)-1] < 0) {
                    if ($trend != 'down') {
                        if ($trend === 'up' && $trendLength >= $minTrendLength) {
                            $finishPrice = $dataRow->candles[$mainColumn]->close;
                            $this->log->alert("UP -> DOWN", [$trendLength, $finishPrice / $startPrice]);
                            if ($finishPrice / $startPrice > 1.03) {
//                                echo "UP -> DOWN", PHP_EOL;
//                                $this->save($dataRow, $trendLength);
                                $this->toExchangeStateSlice($dataRow, $trendLength, [$trendStart]);
                            }
                        }

                        $trend = 'down';
                        $trendLength = 0;
                        $startPrice = $dataRow->candles[$mainColumn]->close;
                        $trendStart = $dataRow->candles[$mainColumn]->start;
                    }
                }

                $trendLength += 1;
            } else {
                $this->log->warn("Cannot calculate MACD");
            }
        }
    }

    private $fileCounter = 0;

    private function save(DataRow $dataRow, $count)
    {
        $count += $count * 4;
        $data = [];
        $h = fopen(sprintf("output/data_%d.csv", $this->fileCounter), "w");
        if ($h === false) {
            $this->log->warn("Cannot create output file");
            return;
        }
        $this->log->warn("Writing output file");
        fputcsv($h, ["id", "timestamp", "price"], ";");
        for ($i = 0; $i < $count && $dataRow->prev !== null; $i++) {
            array_push($data, $dataRow->candles[0]->close);
            $dataRow = $dataRow->prev;
//            print_r($dataRow);
            fputcsv($h, [$i+1, $dataRow->candles[0]->start->getTimestamp(), $dataRow->candles[0]->close], ";");
        }
        fclose($h);
        $this->fileCounter++;
    }

    /**
     * @param \CoinCorp\RateAnalyzer\DataRow $dataRow
     * @param integer                        $length
     * @param \DateTime[]                    $times
     * @return \CoinCorp\RateAnalyzer\ExchangeStateSlice
     */
    private function toExchangeStateSlice(DataRow $dataRow, $length, $times = [])
    {
        $exchangeStateSlice = new ExchangeStateSlice();

        $exchangeStateSlice->series = array_fill(0, sizeof($dataRow->candles), []);

        // Копируем тренд
        for ($i = 0; $i < $length && $dataRow->prev !== null; $i++) {
            foreach ($dataRow->candles as $column => $candle) {
                array_push($exchangeStateSlice->series[$column], [
                    'start' => $candle->start->getTimestamp(),
                    'price' => $candle->close,
                ]);
            }
            $dataRow = $dataRow->prev;
        }

        // Копируем период предшествывающий тренду
        if ($dataRow !== null) {
            for ($i = 0; $i < $length * 4 && $dataRow->prev !== null; $i++) {
                foreach ($dataRow->candles as $column => $candle) {
                    array_push($exchangeStateSlice->series[$column], [
                        'start' => $candle->start->getTimestamp(),
                        'price' => $candle->close,
                    ]);
                }
                $dataRow = $dataRow->prev;
            }
        }

        // TODO: Составить список названий графиков.

        // TODO: Добавить title.

        $timestamps = [];
        foreach ($times as $time) {
            array_push($timestamps, $time->getTimestamp());
        }
        $exchangeStateSlice->verticalLines = $timestamps;

        file_put_contents("output/state".$this->fileCounter.".json", json_encode($exchangeStateSlice));
        $this->fileCounter++;

        return $exchangeStateSlice;
    }
}
