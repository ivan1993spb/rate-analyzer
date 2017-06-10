<?php

namespace CoinCorp\RateAnalyzer;

/**
 * Interface CandleEmitterInterface
 *
 * @package CoinCorp\RateAnalyzer
 */
interface CandleEmitterInterface
{
    /**
     * @return \Generator|\CoinCorp\RateAnalyzer\Candle[]
     */
    public function candles();

    /**
     * Returns candle size in seconds
     *
     * @return integer
     */
    public function getCandleSize();
}
