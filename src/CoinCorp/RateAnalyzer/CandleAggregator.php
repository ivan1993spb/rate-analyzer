<?php

namespace CoinCorp\RateAnalyzer;

use CoinCorp\RateAnalyzer\Exceptions\MismatchCandleSizesException;
use DateTime;

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
     * @param \DateTime $from
     * @param \DateTime $to
     * @return \Generator|\CoinCorp\RateAnalyzer\Candle[][]
     */
    public function next(DateTime $from, DateTime $to)
    {
        /** @var \CoinCorp\RateAnalyzer\Candle[][] $candles */
        $candles = [];

        foreach ($this->candleEmitters as $column => $candleEmitter) {
            $tmp = $candleEmitter->getCandles($from, $to);
            if (count($tmp) === 0) {
                return [];
            }
            array_push($candles, $tmp);
        }

        $indexes = array_fill(0, count($this->candleEmitters), 0);

        while (true) {
            /** @var \DateTime $time */
            $time = null;
            foreach ($candles as $candleColumn) {
                
            }
        }

        foreach ($this->candleEmitters as $column => $candleEmitter) {





            foreach ($candleEmitter->getCandles($from, $to) as $row => $candle) {




                if ($row < count($candleRows)) {
                    $candleRows[$row][$column] = $candle;
                } elseif ($row === count($candleRows)) {
                    array_push($candleRows, array_fill(0, count($this->candleEmitters), null));
                    $candleRows[$row][$column] = $candle;
                } else {
                    array_push($candleRows, array_fill(0, count($this->candleEmitters), null));
                }
            }
        }

        return $candleRows;
    }
}
