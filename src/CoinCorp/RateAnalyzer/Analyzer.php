<?php

namespace CoinCorp\RateAnalyzer;

/**
 * Class Analyzer
 *
 * @package CoinCorp\RateAnalyzer
 */
class Analyzer
{
    const CACHE_SIZE = 1000;

    /**
     * @var \CoinCorp\RateAnalyzer\AggregatorInterface
     */
    private $aggregator;

    /**
     * @var \CoinCorp\RateAnalyzer\Candle[][]
     */
    private $cache;

    /**
     * Analyzer constructor.
     *
     * @param \CoinCorp\RateAnalyzer\AggregatorInterface $aggregator
     */
    public function __construct(AggregatorInterface $aggregator)
    {
        $this->aggregator = $aggregator;
    }

    public function analyze() {
        foreach ($this->aggregator->rows() as $row) {
            // Update cache
            foreach ($row as $column => $candle) {
                if (!is_array($this->cache[$column])) {
                    $this->cache[$column] = [];
                }
                while (sizeof($this->cache[$column]) >= self::CACHE_SIZE) {
                    array_shift($this->cache[$column]);
                }
                array_push($this->cache[$column], $candle);
            }

            foreach ($this->cache as $column => $candles) {
                if (sizeof($candles) === self::CACHE_SIZE) {
                    $real = [];
                    foreach ($candles as $candle) {
                        array_push($real, $candle->close);
                    }
                    print_r(trader_macd($real, 10, 21, 9));
                    exit();
                }
            }
        }
    }
}
