<?php

namespace CoinCorp\RateAnalyzer\Correlation\CandleVariables;

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\Correlation\CandleVariableInterface;

/**
 * Class CandleVariableMACD
 *
 * @package CoinCorp\RateAnalyzer\Correlation\CandleVariables
 */
class CandleVariableMACD implements CandleVariableInterface
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
    private $fastPeriod;

    /**
     * @var int
     */
    private $slowPeriod;

    /**
     * @var int
     */
    private $signalPeriod;

    /**
     * CandleVariableMACD constructor.
     *
     * @param string  $name
     * @param integer $fastPeriod   Number of period for the fast MA. Valid range from 2 to 100000.
     * @param integer $slowPeriod   Number of period for the slow MA. Valid range from 2 to 100000.
     * @param integer $signalPeriod Smoothing for the signal line (nb of period). Valid range from 1 to 100000.
     * @param integer $cacheSize
     * @internal param int $period Number of period. Valid range from 2 to 100000.
     */
    public function __construct($name, $fastPeriod, $slowPeriod, $signalPeriod, $cacheSize)
    {
        $this->name = $name;
        $this->fastPeriod = $fastPeriod;
        $this->slowPeriod = $slowPeriod;
        $this->signalPeriod = $signalPeriod;
        $this->cacheSize = $cacheSize;
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

        $MACD = trader_macd($this->cache, $this->fastPeriod, $this->slowPeriod, $this->signalPeriod);
        if ($MACD === false) {
            return;
        }
        $arr = array_values($MACD[0]);
        if (empty($arr)) {
            return;
        }

        $this->value = (double)$arr[sizeof($arr)-1];
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
