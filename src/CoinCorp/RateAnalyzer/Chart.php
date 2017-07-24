<?php

namespace CoinCorp\RateAnalyzer;

/**
 * Class Chart
 *
 * @package CoinCorp\RateAnalyzer
 */
class Chart
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var \CoinCorp\RateAnalyzer\ChartPoint[][]
     */
    public $series = [];

    /**
     * @var string[]
     */
    public $seriesNames = [];

    /**
     * Chart constructor.
     *
     * @param string   $name
     * @param string[] $seriesNames
     */
    public function __construct($name, $seriesNames = [])
    {
        $this->name = $name;
        $this->seriesNames = $seriesNames;
        $this->series = array_fill(0, sizeof($seriesNames), []);
    }
}
