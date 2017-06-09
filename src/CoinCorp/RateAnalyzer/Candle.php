<?php

namespace CoinCorp\RateAnalyzer;

/**
 * Class Candle
 *
 * @package CoinCorp\RateAnalyzer
 */
class Candle
{
    /**
     * Contains information about exchange, currency and asset
     *
     * @var string
     */
    public $pairSourceName;

    public $start;

    /**
     * @var float
     */
    public $open;

    /**
     * @var float
     */
    public $high;

    /**
     * @var float
     */
    public $low;

    /**
     * @var float
     */
    public $close;

    /**
     * Volume weighted price
     *
     * @var float
     */
    public $vwp;

    /**
     * @var float
     */
    public $volume;

    /**
     * @var integer
     */
    public $trades;

    /**
     * Candle constructor.
     *
     * @param string $pairSourceName
     * @param $start
     * @param float $open
     * @param float $high
     * @param float $low
     * @param float $close
     * @param float $vwp
     * @param float $volume
     * @param integer $trades
     */
    public function __construct($pairSourceName, $start, $open, $high, $low, $close, $vwp, $volume, $trades)
    {

    }
}
