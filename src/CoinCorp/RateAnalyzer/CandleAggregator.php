<?php

namespace CoinCorp\RateAnalyzer;

use CoinCorp\RateAnalyzer\Exceptions\ClosedCandleEmitterException;
use CoinCorp\RateAnalyzer\Exceptions\MismatchCandleSizesException;
use Monolog\Logger;

/**
 * Class Aggregator
 *
 * @package CoinCorp\RateAnalyzer
 */
class CandleAggregator
{
    /**
     * @var \CoinCorp\RateAnalyzer\CandleEmitterInterface[]
     */
    private $candleEmitters = [];

    /**
     * @var \Monolog\Logger
     */
    private $log;

    /**
     * CandleAggregator constructor.
     *
     * @param \Monolog\Logger $log
     */
    public function __construct(Logger $log)
    {
        $this->log = $log;
    }

    /**
     * @param \CoinCorp\RateAnalyzer\CandleEmitterInterface $candleEmitter
     * @throws \CoinCorp\RateAnalyzer\Exceptions\ClosedCandleEmitterException
     * @throws \CoinCorp\RateAnalyzer\Exceptions\MismatchCandleSizesException
     */
    public function addCandleEmitter(CandleEmitterInterface $candleEmitter)
    {
        $candleSize = $candleEmitter->getCandleSize();

        foreach ($this->candleEmitters as $emitter) {
            if ($candleSize !== $emitter->getCandleSize()) {
                throw new MismatchCandleSizesException();
            }
        }

        $generator = $candleEmitter->candles();
        if (!$generator->valid()) {
            throw new ClosedCandleEmitterException();
        }

        /** @var \CoinCorp\RateAnalyzer\Candle $firstCandle */
        $firstCandle = $generator->current();

        $skipSeconds = $candleSize - $firstCandle->start->getTimestamp() % $candleSize;
        $this->log->info("Seconds to skip", [$skipSeconds]);
        $candleEmitter->skipSeconds($skipSeconds);

        array_push($this->candleEmitters, $candleEmitter);
    }

    /**
     * @return \Generator|\CoinCorp\RateAnalyzer\Candle[][]
     */
    public function rows()
    {
        $this->log->info("Starting rows generator");

        /** @var \Generator[] $generators */
        $generators = array_fill(0, count($this->candleEmitters), null);
        foreach ($this->candleEmitters as $column => $candleEmitter) {
            $generators[$column] = $candleEmitter->candles();
        }

        /** @var \DateTime $currentTime */
        $currentTime = null;

        /** @var \CoinCorp\RateAnalyzer\Candle[] $candles */
        $candles = array_fill(0, count($this->candleEmitters), null);

        while (true) {
            $column = 0;

            while ($column < count($generators)) {
                $generator = $generators[$column];

                if (!$generator->valid()) {
                    // Finish aggregator if any candle emitter closed
                    break 2;
                }

                /** @var \CoinCorp\RateAnalyzer\Candle $candle */
                $candle = $generator->current();

                if (empty($currentTime) || $currentTime->getTimestamp() < $candle->start->getTimestamp()) {
                    $currentTime = $candle->start;
                    $this->log->info("New time cursor", [$currentTime]);
                    if ($column != 0) {
                        $column = 0;
                    }
                    continue;
                }

                while ($currentTime->getTimestamp() > $candle->start->getTimestamp()) {
                    $this->log->info("Skip candles to sync generator with current time", [$this->candleEmitters[$column]->getName(), $candle->start, $currentTime]);
                    $generator->next();
                    if (!$generator->valid()) {
                        // Candle emitter closed
                        break 3;
                    }
                    $candle = $generator->current();
                }

                if ($currentTime->getTimestamp() < $candle->start->getTimestamp()) {
                    $this->log->warn("Cannot sync time");
                }

                $candles[$column] = $candle;

                $column++;
            }

            $this->log->info("Output row");

            yield $candles;

            foreach ($generators as $generator) {
                $generator->next();
            }
        }
    }
}
