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
class CandleAggregator implements AggregatorInterface
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
     * @var integer
     */
    private $historySize;

    /**
     * CandleAggregator constructor.
     *
     * @param \Monolog\Logger $log
     * @param integer         $historySize
     */
    public function __construct(Logger $log, $historySize = 0)
    {
        $this->log = $log;
        $this->historySize = $historySize;
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
        $this->log->info("Seconds to skip", [$candleEmitter->getName(), $skipSeconds]);
        $candleEmitter->skipSeconds($skipSeconds);

        array_push($this->candleEmitters, $candleEmitter);
    }

    /**
     * @return integer
     */
    public function capacity()
    {
       return sizeof($this->candleEmitters);
    }

    /**
     * @return \Generator|\CoinCorp\RateAnalyzer\DataRow[]
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

        /** @var \CoinCorp\RateAnalyzer\DataRow $prevDataRow */
        $prevDataRow = null;

        while (true) {
            /** @var \CoinCorp\RateAnalyzer\Candle[] $candles */
            $candles = array_fill(0, count($this->candleEmitters), null);

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
                    $this->log->info("Skip candles to sync generator with current time", [$this->candleEmitters[$column]->getName(),
                        $candle->start, $currentTime]);
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

            $this->log->info("Output row", [$currentTime]);

            $dataRow = new DataRow($currentTime, $candles, $prevDataRow);

            yield $dataRow;

            $prevDataRow = $dataRow;

            // Clear history
            for ($i = 0; $i < $this->historySize && $dataRow->prev !== null; $i++) {
                $dataRow = $dataRow->prev;
            }
            $dataRow->prev = null;

            foreach ($generators as $generator) {
                $generator->next();
            }
        }
    }
}
