<?php

namespace CoinCorp\RateAnalyzer;

use CoinCorp\RateAnalyzer\Exceptions\ZeroBatchSizeException;
use DateTime;

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
    public $cache = [];

    /**
     * CandleBatcher constructor.
     *
     * @param \CoinCorp\RateAnalyzer\CandleEmitterInterface $candleEmitter
     * @param integer $batchSize
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
     * @param \DateTime $from
     * @param \DateTime $to
     * @return \CoinCorp\RateAnalyzer\Candle[]
     */
    public function getCandles(DateTime $from, DateTime $to)
    {
        if ($this->batchSize === 1) {
            return $this->candleEmitter->getCandles($from, $to);
        }

        /** @var \CoinCorp\RateAnalyzer\Candle[] $candles */
        $candles = []; // Для больших свечей

        foreach ($this->candleEmitter->getCandles($from, $to) as $candle) {
            array_push($this->cache, $candle);

            if (count($this->cache) === $this->batchSize) {
                $bigCandle = $this->cache[0];
                $prices = $this->cache[0]->vwp * $this->cache[0]->volume;

                for ($i = 1; $i < count($this->cache); $i++) {
                    $smallCandle = $this->cache[$i];

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

                $this->cache = [];
                array_push($candles, $bigCandle);
            }
        }

        return $candles;
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
}
