<?php

namespace CoinCorp\RateAnalyzer\Correlation\CandleVariables;

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\Correlation\CandleVariableInterface;

/**
 * Class CandleVariableClosePrice
 *
 * @package CoinCorp\RateAnalyzer\Correlation\CandleVariables
 */
class CandleVariableClosePrice implements CandleVariableInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var float|null
     */
    private $value = null;

    /**
     * CandleVariableClosePrice constructor.
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
        $this->value = $candle->close;
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
        return is_double($this->value);
    }

    /**
     * @return float
     */
    public function value()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function name()
    {
        return $this->name;
    }
}
