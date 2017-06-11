<?php

namespace CoinCorp\RateAnalyzer;

use CoinCorp\RateAnalyzer\Exceptions\ZeroBatchSizeException;

/**
 * Class CandleBatcher
 *
 * @package CoinCorp\RateAnalyzer
 */
class CandleBatcher implements CandleEmitterInterface
{
    /**
     * @var \CoinCorp\RateAnalyzer\CandleEmitterInterface
     */
    private $candleEmitter;

    /**
     * @var integer
     */
    private $batchSize;

    /**
     * @var \CoinCorp\RateAnalyzer\Candle[]
     */
    private $cache = [];

    /**
     * CandleBatcher constructor.
     *
     * @param \CoinCorp\RateAnalyzer\CandleEmitterInterface $candleEmitter
     * @param integer                                       $batchSize
     * @throws \CoinCorp\RateAnalyzer\Exceptions\ZeroBatchSizeException
     */
    public function __construct(CandleEmitterInterface $candleEmitter, $batchSize)
    {
        if ($batchSize === 0) {
            throw new ZeroBatchSizeException();
        }
        $this->candleEmitter = $candleEmitter;
        $this->batchSize = $batchSize;
    }

    /**
     * @return \Generator|\CoinCorp\RateAnalyzer\Candle[]
     */
    public function candles()
    {
        if ($this->batchSize === 1) {
            foreach ($this->candleEmitter->candles() as $candle) {
                yield $candle;
            }
        } else {
            foreach ($this->candleEmitter->candles() as $candle) {
                array_push($this->cache, $candle);

                if (count($this->cache) === $this->batchSize) {
                    $bigCandle = array_shift($this->cache);
                    $prices = $bigCandle->vwp * $bigCandle->volume;

                    while (count($this->cache) > 0) {
                        $smallCandle = array_shift($this->cache);

                        $bigCandle->high = max($bigCandle->high, $smallCandle->high);
                        $bigCandle->low = min($bigCandle->low, $smallCandle->low);
                        $bigCandle->close = $smallCandle->close;
                        $bigCandle->volume += $smallCandle->volume;
                        $bigCandle->trades += $smallCandle->trades;

                        $prices += $smallCandle->vwp * $smallCandle->volume;
                    }

                    if ($bigCandle->volume > 0) {
                        $bigCandle->vwp = $prices / $bigCandle->volume;
                    } else {
                        $bigCandle->vwp = $bigCandle->open;
                    }

                    yield $bigCandle;
                }
            }
        }
    }

    /**
     * Returns candle size in seconds
     *
     * @return integer
     */
    public function getCandleSize()
    {
        return $this->batchSize * $this->candleEmitter->getCandleSize();
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->candleEmitter->getName();
    }

    /**
     * @param integer $seconds
     * @return void
     */
    public function skipSeconds($seconds)
    {
        $this->candleEmitter->skipSeconds($seconds);
    }
}
