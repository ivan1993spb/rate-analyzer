<?php

namespace CoinCorp\RateAnalyzer;

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

        array_push($this->candleEmitters, $candleEmitter);
    }

    /**
     * @return \Generator|\CoinCorp\RateAnalyzer\Candle[][]
     */
    public function rows()
    {
        $this->log->info("Starting rows generator");

        /** @var \CoinCorp\RateAnalyzer\Candle[] $candles */
        $candles = array_fill(0, count($this->candleEmitters), null);

        /** @var \DateTime $currentTime */
        $currentTime = null;

        // Initialize generators
        /** @var \Generator|\CoinCorp\RateAnalyzer\Candle[] $generators */
        $generators = array_fill(0, count($this->candleEmitters), null);
        foreach ($this->candleEmitters as $column => $candleEmitter) {
            $generators[$column] = $candleEmitter->candles();
        }

        while (true) {
            foreach ($generators as $column => $generator) {
                if (!$generator->valid()) {
                    // Finish aggregator if any candle emitter closed
                    break 2;
                }

                /** @var \CoinCorp\RateAnalyzer\Candle $candle */
                $candle = $generator->current();

                if (empty($currentTime)) {
                    $currentTime = $candle->start;
                    $candles[$column] = $candle;
                } elseif ($currentTime->getTimestamp() === $candle->start->getTimestamp()) {
                    $candles[$column] = $candle;
                } elseif ($currentTime->getTimestamp() > $candle->start->getTimestamp()) {
                    do {
                        $generator->next();
                        $candle = $generator->current();
                    } while ($currentTime->getTimestamp() > $candle->start->getTimestamp());
                    $candles[$column] = $candle;
                } elseif ($currentTime->getTimestamp() < $candle->start->getTimestamp()) {
                    $currentTime = $candle->start;
                    $candles[$column] = $candle;
                }
            }

            foreach ($candles as $candle) {
                if ($candle->start->getTimestamp() !== $currentTime->getTimestamp()) {
                    continue 2;
                }
            }

            $this->log->info("Output row");

            yield $candles;

            foreach ($generators as $generator) {
                $generator->next();
            }
        }
    }
}
