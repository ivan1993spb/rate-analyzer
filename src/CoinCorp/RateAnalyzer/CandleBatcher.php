<?php

namespace CoinCorp\RateAnalyzer;

use CoinCorp\RateAnalyzer\Exceptions\ZeroBatchSizeException;
use DateInterval;

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
        } elseif ($this->batchSize > 1) {
            $generator = $this->candleEmitter->candles();

            if ($generator->valid()) {
                /** @var \DateTime $currentTime */
                $currentTime = clone $generator->current()->start;

                // Интервал большой свечей
                $bigCandleInterval = new DateInterval(sprintf("PT%dS", $this->getCandleSize()));

                /** @var \CoinCorp\RateAnalyzer\Candle[] $cache */
                $cache = [];

                while (true) {
                    /** @var \CoinCorp\RateAnalyzer\Candle $candle */
                    $candle = $generator->current();

                    if (sizeof($cache) === $this->batchSize || ($currentTime->getTimestamp() + $this->getCandleSize() <= $candle->start->getTimestamp() && sizeof($cache) > 0)) {
                        $bigCandle = $this->mergeCandles($cache);
                        $bigCandle->start = clone $currentTime;
                        yield $bigCandle;

                        $cache = [];
                        $currentTime->add($bigCandleInterval);
                    }

                    array_push($cache, $candle);

                    $generator->next();
                    if (!$generator->valid()) {
                        if (sizeof($cache) > 0) {
                            $bigCandle = $this->mergeCandles($cache);
                            $bigCandle->start = clone $currentTime;
                            yield $bigCandle;
                        }
                        break;
                    }
                }
            }
        }
    }

    /**
     * @param \CoinCorp\RateAnalyzer\Candle[] $candles
     * @return \CoinCorp\RateAnalyzer\Candle
     */
    private function mergeCandles($candles)
    {
        $smallCandle = array_shift($candles);

        $bigCandle = new Candle($this->getName(), $smallCandle->start, $this->getCandleSize(),
            $smallCandle->open, $smallCandle->high, $smallCandle->low, $smallCandle->close,
            $smallCandle->vwp, $smallCandle->volume, $smallCandle->trades);

        $prices = $smallCandle->vwp * $smallCandle->volume;

        while (count($candles) > 0) {
            $smallCandle = array_shift($candles);

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

        return $bigCandle;
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
