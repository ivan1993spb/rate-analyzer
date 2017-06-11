<?php

namespace CoinCorp\RateAnalyzer;

/**
 * Class Analyzer
 *
 * @package CoinCorp\RateAnalyzer
 */
class Analyzer
{
    const CACHE_SIZE = 1000;

    /**
     * @var \CoinCorp\RateAnalyzer\AggregatorInterface
     */
    private $aggregator;

    /**
     * Analyzer constructor.
     *
     * @param \CoinCorp\RateAnalyzer\AggregatorInterface $aggregator
     */
    public function __construct(AggregatorInterface $aggregator)
    {
        $this->aggregator = $aggregator;
    }

    public function analyze() {
        foreach ($this->aggregator->rows() as $row) {

        }
    }
}
