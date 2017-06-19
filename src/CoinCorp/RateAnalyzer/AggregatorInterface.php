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
     * @return \Generator|\CoinCorp\RateAnalyzer\DataRow[]
     */
    public function rows();

    /**
     * @return integer
     */
    public function capacity();

    /**
     * @return string[]
     */
    public function emittersNames();
}
