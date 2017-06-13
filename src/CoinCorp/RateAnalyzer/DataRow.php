<?php

namespace CoinCorp\RateAnalyzer;

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
     */
    public function __construct($time, $candles, $prev = null)
    {
        $this->time = $time;
        $this->candles = $candles;
        $this->prev = $prev;
    }
}
