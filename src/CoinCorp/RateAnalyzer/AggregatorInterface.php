<?php

namespace CoinCorp\RateAnalyzer;

/**
 * Interface AggregatorInterface
 *
 * @package CoinCorp\RateAnalyzer
 */
interface AggregatorInterface
{
    /**
     * @return \Generator|\CoinCorp\RateAnalyzer\Candle[][]
     */
    public function rows();
}
