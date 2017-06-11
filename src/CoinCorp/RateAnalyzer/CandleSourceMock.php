<?php

namespace CoinCorp\RateAnalyzer;

/**
 * Class CandleSourceMock
 *
 * @package CoinCorp\RateAnalyzer
 */
class CandleSourceMock implements CandleEmitterInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var integer
     */
    private $candleSize;

    /**
     * @var \CoinCorp\RateAnalyzer\Candle[]
     */
    private $candles;

    /**
     * CandleSourceMock constructor.
     *
     * @param string                          $name
     * @param \CoinCorp\RateAnalyzer\Candle[] $candles
     * @param integer                         $candleSize
     */
    public function __construct($name, $candles, $candleSize = 60)
    {
        $this->name = $name;
        $this->candles = $candles;
        $this->candleSize = $candleSize;
    }

    /**
     * @return \Generator|\CoinCorp\RateAnalyzer\Candle[]
     */
    public function candles()
    {
        foreach ($this->candles as $candle) {
            yield $candle;
        }
    }

    /**
     * Returns candle size in seconds
     *
     * @return integer
     */
    public function getCandleSize()
    {
        return $this->candleSize;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param integer $seconds
     * @return void
     */
    public function skipSeconds($seconds)
    {
        // TODO: Implement skipSeconds() method.
    }
}
