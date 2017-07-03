<?php

namespace CoinCorp\RateAnalyzer\Scanners;

use CoinCorp\RateAnalyzer\AggregatorInterface;
use CoinCorp\RateAnalyzer\TimeRange;
use DateInterval;
use Monolog\Logger;

/**
 * Class AggregatorScanner
 *
 * @package CoinCorp\RateAnalyzer\Scanners
 */
class AggregatorScanner
{
    /**
     * @var \CoinCorp\RateAnalyzer\AggregatorInterface
     */
    private $aggregator;

    /**
     * @var integer
     */
    private $candleSize;

    /**
     * @var \Monolog\Logger
     */
    private $log;

    /**
     * AggregatorScanner constructor.
     *
     * @param \CoinCorp\RateAnalyzer\AggregatorInterface $aggregator
     * @param integer                                    $candleSize Candle size in seconds
     * @param \Monolog\Logger                            $log
     */
    public function __construct(AggregatorInterface $aggregator, $candleSize, Logger $log)
    {
        $this->aggregator = $aggregator;
        $this->candleSize = $candleSize;
        $this->log = $log;
    }

    /**
     * @return \Generator|\CoinCorp\RateAnalyzer\TimeRange[]
     */
    public function scan()
    {
        $candleInterval = new DateInterval(sprintf("PT%dS", $this->candleSize));
        $this->log->info("Start scanning aggregator", [
            'names'             => $this->aggregator->emittersNames(),
            'capacity'          => $this->aggregator->capacity(),
            'candleSizeSeconds' => $this->candleSize,
        ]);

        $generator = $this->aggregator->rows();

        /** @var \DateTime|null $currentTime */
        $currentTime = null;

        /** @var \DateTime|null $startTime */
        $startTime = null;

        /** @var \DateTime|null $finishTime */
        $finishTime = null;

        while (true) {
            if (!$generator->valid()) {
                if ($startTime !== null && $finishTime !== null) {
                    yield new TimeRange($startTime, $finishTime);
                }
                break;
            }

            /** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
            $dataRow = $generator->current();

            if ($currentTime === null) {
                $currentTime = $dataRow->time;
            }

            if ($startTime === null) {
                $startTime = clone $dataRow->time;
            }

            if ($currentTime->getTimestamp() < $dataRow->time->getTimestamp()) {
                yield new TimeRange($startTime, $finishTime);
                $currentTime = $dataRow->time;
                $startTime = clone $dataRow->time;
            }

            $currentTime->add($candleInterval);
            $finishTime = $currentTime;
            $generator->next();
        }
    }
}
