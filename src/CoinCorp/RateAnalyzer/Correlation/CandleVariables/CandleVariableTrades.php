<?php

namespace CoinCorp\RateAnalyzer\Correlation\CandleVariables;

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\Correlation\CandleVariableInterface;

/**
 * Class CandleVariableTrades
 *
 * @package CoinCorp\RateAnalyzer\Correlation\CandleVariables
 */
class CandleVariableTrades implements CandleVariableInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var integer|null
     */
    private $value = null;

    /**
     * CandleVariableTrades constructor.
     *
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * @param Candle $candle
     * @return void
     */
    public function update(Candle $candle)
    {
        $this->value = $candle->trades;
    }

    /**
     * @return void
     */
    public function free()
    {
        $this->value = null;
    }

    /**
     * @return bool
     */
    public function ready()
    {
        return is_integer($this->value);
    }

    /**
     * @return float
     */
    public function value()
    {
        return doubleval($this->value);
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->name;
    }
}
