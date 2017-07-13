<?php

namespace CoinCorp\RateAnalyzer\Correlation;

use CoinCorp\RateAnalyzer\Candle;

/**
 * Interface CandleVariableInterface
 *
 * @package CoinCorp\RateAnalyzer\Correlation
 */
interface CandleVariableInterface
{
    /**
     * @param Candle $candle
     * @return void
     */
    public function update(Candle $candle);

    /**
     * @return void
     */
    public function free();

    /**
     * @return bool
     */
    public function ready();

    /**
     * @return float
     */
    public function value();

    /**
     * @return string
     */
    public function name();
}
