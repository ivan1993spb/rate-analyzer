<?php

namespace CoinCorp\RateAnalyzer;

use CoinCorp\RateAnalyzer\Exceptions\InvalidCapacityException;

/**
 * Class DataRow
 *
 * @package CoinCorp\RateAnalyzer
 */
class DataRow
{
    /**
     * @var \CoinCorp\RateAnalyzer\Candle[]
     */
    public $candles;

    /**
     * @var \DateTime
     */
    public $time;

    /**
     * @var null|\CoinCorp\RateAnalyzer\DataRow
     */
    public $prev = null;

    /**
     * DataRow constructor.
     *
     * @param \DateTime                           $time
     * @param \CoinCorp\RateAnalyzer\Candle[]     $candles
     * @param null|\CoinCorp\RateAnalyzer\DataRow $prev
     * @throws \CoinCorp\RateAnalyzer\Exceptions\InvalidCapacityException
     */
    public function __construct($time, $candles, $prev = null)
    {
        if ($prev !== null) {
            if (sizeof($prev->candles) !== sizeof($candles)) {
                throw new InvalidCapacityException();
            }
        }
        $this->time = $time;
        $this->candles = $candles;
        $this->prev = $prev;
    }
}
