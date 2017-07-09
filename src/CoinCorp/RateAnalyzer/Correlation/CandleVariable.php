<?php

namespace CoinCorp\RateAnalyzer\Correlation;

use CoinCorp\RateAnalyzer\Candle;

/**
 * Class CandleVariable
 *
 * @package CoinCorp\RateAnalyzer\Correlation
 */
class CandleVariable
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var float
     */
    public $value = 0.0;

    /**
     * @var callable|null
     */
    private $update = null;

    /**
     * CandleVariable constructor.
     *
     * @param string   $name
     * @param callable $update
     */
    public function __construct($name, callable $update)
    {
        $this->name = $name;
        $this->update = $update;
    }

    public function update(Candle $candle)
    {
        if (is_callable($this->update)) {
            $this->value = call_user_func($this->update, $candle);
        }
    }

    public function free()
    {
        $this->update = null;
    }
}
