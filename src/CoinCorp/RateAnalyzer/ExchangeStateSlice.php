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

    // TODO: Добавить возможность совмещать графики.

    /**
     * @var \CoinCorp\RateAnalyzer\Candle[][]
     */
    public $series = [];

    /**
     * @var string[]
     */
    public $seriesNames = [];
}
