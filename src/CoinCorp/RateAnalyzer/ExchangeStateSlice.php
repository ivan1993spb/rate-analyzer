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
     * @var \CoinCorp\RateAnalyzer\Chart[]
     */
    public $charts = [];
}
