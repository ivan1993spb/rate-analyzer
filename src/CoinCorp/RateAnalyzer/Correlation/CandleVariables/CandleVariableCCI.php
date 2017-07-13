<?php

namespace CoinCorp\RateAnalyzer\Correlation\CandleVariables;

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\Correlation\CandleVariableInterface;

/**
 * Class CandleVariableCCI
 *
 * @package CoinCorp\RateAnalyzer\Correlation\CandleVariables
 */
class CandleVariableCCI implements CandleVariableInterface
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
    private $cacheHigh = [];

    /**
     * @var float[]
     */
    private $cacheLow = [];

    /**
     * @var float[]
     */
    private $cacheClose = [];

    /**
     * @var int
     */
    private $cacheSize;

    /**
     * @var int
     */
    private $period;

    /**
     * CandleVariableCCI constructor.
     *
     * @param string  $name
     * @param integer $period Number of period. Valid range from 2 to 100000.
     */
    public function __construct($name, $period)
    {
        $this->name = $name;
        $this->period = $period;
        $this->cacheSize = $period;
    }

    /**
     * @param Candle $candle
     * @return void
     */
    public function update(Candle $candle)
    {
        array_push($this->cacheHigh, $candle->high);
        while (sizeof($this->cacheHigh) > $this->cacheSize) {
            array_shift($this->cacheHigh);
        }
        array_push($this->cacheLow, $candle->low);
        while (sizeof($this->cacheLow) > $this->cacheSize) {
            array_shift($this->cacheLow);
        }
        array_push($this->cacheClose, $candle->close);
        while (sizeof($this->cacheClose) > $this->cacheSize) {
            array_shift($this->cacheClose);
        }

        $CCI = trader_cci($this->cacheHigh, $this->cacheLow, $this->cacheClose, $this->period);
        if ($CCI === false) {
            return;
        }
        $arr = array_values($CCI);
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
        $this->cacheHigh = [];
        $this->cacheLow = [];
        $this->cacheClose = [];
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
