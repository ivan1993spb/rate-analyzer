<?php

namespace CoinCorp\RateAnalyzer;

/**
 * Class ChartPoint
 *
 * @package CoinCorp\RateAnalyzer
 */
class ChartPoint
{
    /**
     * @var float|integer
     */
    public $x;

    /**
     * @var float|integer
     */
    public $y;

    /**
     * ChartPoint constructor.
     *
     * @param float|integer $x
     * @param float|integer $y
     */
    public function __construct($x, $y)
    {
        $this->x = $x;
        $this->y = $y;
    }
}
