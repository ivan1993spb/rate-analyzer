<?php

namespace CoinCorp\RateAnalyzer\Correlation\CandleVariables;

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\Correlation\CandleVariableInterface;

/**
 * Class CandleVariableEMA
 *
 * @package CoinCorp\RateAnalyzer\Correlation\CandleVariables
 */
class CandleVariableEMA implements CandleVariableInterface
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
     * @var float[]
     */
    private $cache = [];

    /**
     * @var int
     */
    private $cacheSize;

    /**
     * @var int
     */
    private $period;

    /**
     * CandleVariableEMA constructor.
     *
     * @param string  $name
     * @param integer $period    Number of period. Valid range from 2 to 100000.
     * @param int     $cacheSize
     */
    public function __construct($name, $period, $cacheSize = 0)
    {
        $this->name = $name;
        $this->period = $period;
        $this->cacheSize = $cacheSize > 0 ? $cacheSize : $period;
    }

    /**
     * @param Candle $candle
     * @return void
     */
    public function update(Candle $candle)
    {
        array_push($this->cache, $candle->close);
        while (sizeof($this->cache) > $this->cacheSize) {
            array_shift($this->cache);
        }

        $EMA = trader_ema($this->cache, $this->period);
        if ($EMA === false) {
            return;
        }
        $arr = array_values($EMA);
        if (empty($arr)) {
            return;
        }

        $this->value = $arr[sizeof($arr)-1];
    }

    /**
     * @return void
     */
    public function free()
    {
        $this->cache = [];
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
