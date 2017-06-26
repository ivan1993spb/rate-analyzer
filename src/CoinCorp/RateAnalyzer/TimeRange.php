<?php

namespace CoinCorp\RateAnalyzer;

/**
 * Class TimeRange
 *
 * @package CoinCorp\RateAnalyzer
 */
class TimeRange
{
    /**
     * @var \DateTime
     */
    public $start = null;

    /**
     * @var \DateTime
     */
    public $finish = null;

    /**
     * TimeRange constructor.
     *
     * @param \DateTime|null $start
     * @param \DateTime|null $finish
     */
    public function __construct($start = null, $finish = null)
    {
        $this->start = $start;
        $this->finish = $finish;
    }
}
