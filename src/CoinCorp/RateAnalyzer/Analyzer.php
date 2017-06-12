<?php

namespace CoinCorp\RateAnalyzer;

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
     * Analyzer constructor.
     *
     * @param \CoinCorp\RateAnalyzer\AggregatorInterface $aggregator
     */
    public function __construct(AggregatorInterface $aggregator)
    {
        $this->aggregator = $aggregator;
        ini_set("trader.real_precision", 10);
    }

    public function analyze()
    {
        foreach ($this->aggregator->rows() as $row) {
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

            foreach ($this->cache as $column => $candles) {
                if (sizeof($candles) === self::CACHE_SIZE) {
                    $real = [];
                    foreach ($candles as $candle) {
                        array_push($real, $candle->close);
                    }
                    var_dump($real);
                    var_dump(TRADER_ERR_SUCCESS, TRADER_ERR_LIB_NOT_INITIALIZE, TRADER_ERR_BAD_PARAM);
                    var_dump(trader_macd($real, 10, 21, 9), trader_errno());
                    exit;
                }
            }
        }
    }
}
