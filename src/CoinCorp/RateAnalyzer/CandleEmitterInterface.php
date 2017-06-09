<?php

namespace CoinCorp\RateAnalyzer;

use DateTime;

/**
 * Interface CandleEmitterInterface
 *
 * @package CoinCorp\RateAnalyzer
 */
interface CandleEmitterInterface
{
    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @return \CoinCorp\RateAnalyzer\Candle[]
     */
    public function getCandles(DateTime $from, DateTime $to);

    /**
     * Returns candle size in seconds
     *
     * @return integer
     */
    public function getCandleSize();
}
