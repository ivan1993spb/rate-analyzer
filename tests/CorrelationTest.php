<?php

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\CandleAggregator;
use CoinCorp\RateAnalyzer\CandleSourceMock;
use CoinCorp\RateAnalyzer\Correlation\Correlation;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Class CorrelationTest
 */
class CorrelationTest extends TestCase
{
    /**
     *
     */
    public function testCorrelation()
    {
        $firstSource = new CandleSourceMock("first_mock", [
            new Candle("first_mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), 60, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("first_mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), 60, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("first_mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), 60, 0.2, 3.0, 0.1, 0.8, 1.2, 7.5, 6),
            new Candle("first_mock", new DateTime("Sun, 11 Jun 2017 09:24:00 +0000"), 60, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("first_mock", new DateTime("Sun, 11 Jun 2017 09:25:00 +0000"), 60, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
        ]);

        $secondSource = new CandleSourceMock("second_mock", [
            new Candle("second_mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), 60, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("second_mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("second_mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.2, 8.0, 7),
            new Candle("second_mock", new DateTime("Sun, 11 Jun 2017 09:24:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("second_mock", new DateTime("Sun, 11 Jun 2017 09:25:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ]);

        $logger = new Logger("test");
        $logger->pushHandler(new NullHandler());

        $aggregator = new CandleAggregator($logger);
        $aggregator->addCandleEmitter($firstSource);
        $aggregator->addCandleEmitter($secondSource);

        $correlation = new Correlation($aggregator, $logger, 'test.xlsx');

        $correlation->findCorrelation();
    }
}
