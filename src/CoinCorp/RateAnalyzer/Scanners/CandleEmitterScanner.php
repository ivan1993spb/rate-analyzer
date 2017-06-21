<?php

namespace CoinCorp\RateAnalyzer\Scanners;

use CoinCorp\RateAnalyzer\CandleEmitterInterface;
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
     * @param \Monolog\Logger $log
     */
    public function __construct(CandleEmitterInterface $candleEmitter, Logger $log)
    {
        $this->candleEmitter = $candleEmitter;
        $this->log = $log;
    }

    public function scan()
    {
        $candleSize = $this->candleEmitter->getCandleSize();
        $candleInterval = new DateInterval(sprintf("PT%dS", $candleSize));
        $this->log->info("Start scanning candle emitter", [$this->candleEmitter->getName(), $candleSize]);

        /** @var \DateTime|null $currentTime */
        $currentTime = null;

        /** @var \DateTime|null $startTime */
        $startTime = null;

        /** @var \DateTime|null $finishTime */
        $finishTime = null;

        /** @var integer $count */
        $count = 0;

        foreach ($this->candleEmitter->candles() as $candle) {
            if ($currentTime === null) {
                // Текущее время не задано - первый запуск
                $currentTime = $candle->start;
                $this->log->info("Create time cursor", [$currentTime]);
                // Первая свеча - начало первого периода
                $startTime = clone $candle->start;
                $this->log->info("Create time range start point", [$startTime]);
                $finishTime = null;
                $count += 1;
                $this->log->info("Start time range number", [$count]);
                continue;
            }

            $currentTime = $currentTime->add($candleInterval);

            if ($currentTime->getTimestamp() > $candle->start->getTimestamp()) {
                // Лишняя свеча
                continue;
            }

            if ($currentTime->getTimestamp() < $candle->start->getTimestamp()) {
                // Разрыв между свечами: фиксируем промежуток
                $finishTime = $currentTime;
                $this->log->info("Time range", [$count, $startTime, $finishTime]);
                $currentTime = null;
                $startTime = null;
                $finishTime = null;
            }
        }

        $this->log->info("Count", [$count]);
    }
}
