<?php

namespace CoinCorp\RateAnalyzer;

/**
 * Class ExchangeStateSlice
 *
 * @package CoinCorp\RateAnalyzer
 */
class ExchangeStateSlice
{
    /**
     * @var string
     */
    public $title;

    /**
     * @var integer[]
     */
    public $verticalLines = [];

    /**
     * @var \CoinCorp\RateAnalyzer\Candle[][]
     */
    public $series = [];

    /**
     * @var string[]
     */
    public $seriesNames = [];
}
