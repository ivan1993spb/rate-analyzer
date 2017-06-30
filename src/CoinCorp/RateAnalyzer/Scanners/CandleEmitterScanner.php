<?php

namespace CoinCorp\RateAnalyzer\Scanners;

use CoinCorp\RateAnalyzer\CandleEmitterInterface;
use CoinCorp\RateAnalyzer\TimeRange;
use DateInterval;
use Monolog\Logger;

/**
 * Class CandleEmitterScanner
 *
 * @package CoinCorp\RateAnalyzer\Scanners
 */
class CandleEmitterScanner
{
    /**
     * @var \CoinCorp\RateAnalyzer\CandleEmitterInterface
     */
    private $candleEmitter;

    /**
     * @var \Monolog\Logger
     */
    private $log;

    /**
     * CandleEmitterScanner constructor.
     *
     * @param \CoinCorp\RateAnalyzer\CandleEmitterInterface $candleEmitter
     * @param \Monolog\Logger                               $log
     */
    public function __construct(CandleEmitterInterface $candleEmitter, Logger $log)
    {
        $this->candleEmitter = $candleEmitter;
        $this->log = $log;
    }

    /**
     * @return \Generator|\CoinCorp\RateAnalyzer\TimeRange[]
     */
    public function scan()
    {
        $candleSize = $this->candleEmitter->getCandleSize();
        $candleInterval = new DateInterval(sprintf("PT%dS", $candleSize));
        $this->log->info("Start scanning candle emitter", [$this->candleEmitter->getName(), $candleSize]);

        $candleGenerator = $this->candleEmitter->candles();

        /** @var \DateTime|null $currentTime */
        $currentTime = null;

        /** @var \DateTime|null $startTime */
        $startTime = null;

        /** @var \DateTime|null $finishTime */
        $finishTime = null;

        while (true) {
            if (!$candleGenerator->valid()) {
                if ($startTime !== null && $finishTime !== null) {
                    yield new TimeRange($startTime, $finishTime);
                }
                break;
            }

            /** @var \CoinCorp\RateAnalyzer\Candle $candle */
            $candle = $candleGenerator->current();

            if ($currentTime === null) {
                $currentTime = $candle->start;
            }

            if ($startTime === null) {
                $startTime = clone $candle->start;
            }

            if ($currentTime->getTimestamp() < $candle->start->getTimestamp()) {
                yield new TimeRange($startTime, $finishTime);
                $currentTime = $candle->start;
                $startTime = clone $candle->start;
            }

            $currentTime->add($candleInterval);
            $finishTime = $currentTime;
            $candleGenerator->next();
        }
    }
}
