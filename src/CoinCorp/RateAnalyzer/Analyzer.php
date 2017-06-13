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
    const CACHE_SIZE = 50;

    /**
     * @var \CoinCorp\RateAnalyzer\AggregatorInterface
     */
    private $aggregator;

    /**
     * @var \CoinCorp\RateAnalyzer\Candle[][]
     */
    private $cache;

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
        // Return if nothing to analyze
        if ($this->aggregator->capacity() === 0) {
            return;
        }

        foreach ($this->aggregator->rows() as $row) {
            if (sizeof($row) !== $this->aggregator->capacity()) {
                throw new InvalidCapacityException();
            }

            // Update cache
            foreach ($row as $column => $candle) {
                if (!isset($this->cache[$column])) {
                    $this->cache[$column] = [];
                }
                while (sizeof($this->cache[$column]) >= self::CACHE_SIZE) {
                    array_shift($this->cache[$column]);
                }
                array_push($this->cache[$column], $candle);
            }

//            foreach ($this->cache as $column => $candles) {
                if (sizeof($this->cache[0]) === self::CACHE_SIZE) {
//                    $res[$column] = [];
                    $real = [];
                    foreach ($this->cache[0] as $candle) {
                        array_push($real, $candle->close);
                    }
//                    var_dump($real);
//                    var_dump(TRADER_ERR_SUCCESS, TRADER_ERR_LIB_NOT_INITIALIZE, TRADER_ERR_BAD_PARAM);
//                    var_dump(trader_macd($real, 10, 21, 9), trader_errno());
                    $macd = trader_macd($real, 10, 21, 9);
                    $vals = array_values($macd[0]);
                    if (trader_errno() === TRADER_ERR_SUCCESS) {

//                        if ($vals[0] > $vals[sizeof($vals)-1]) {
//                            continue;
//                        }
                        foreach ($vals as $val) {
                            if ($val < 0) {
                                continue 2;
                            }
                        }
                        $this->log->info("MACD", $vals);
                    } else {
                        continue;
                    }
                } else {
                    continue;
                }
//            }
//            echo json_encode($res);
//            exit();

            $j = 0;
            while (true) {
                $row = [$j + 1];
                for ($i = 0; $i < $this->aggregator->capacity(); $i++) {
                    if (!isset($this->cache[$i][$j])) {
                        break 2;
                    }
                    array_push($row, $this->cache[$i][$j]->start->getTimestamp(), $this->cache[$i][$j]->close);
                }
                fputcsv(STDOUT, $row, ';');
                $j++;
            }
            exit();
        }
    }
}
