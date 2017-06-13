<?php

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\CandleAggregator;
use CoinCorp\RateAnalyzer\CandleSourceMock;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Class CandleAggregatorTest
 */
class CandleAggregatorTest extends TestCase
{
    public function testCapacityReturnsZeroForEmptyAggregator()
    {
        $logger = new Logger("test");
        $logger->pushHandler(new NullHandler());
        $aggregator = new CandleAggregator($logger);
        $this->assertEquals(0, $aggregator->capacity());
    }

    public function testCapacityReturnsValidCountOfEmittersInAggregator()
    {
        $logger = new Logger("test");
        $logger->pushHandler(new NullHandler());
        $aggregator = new CandleAggregator($logger);

        for ($i = 0; $i < 10; $i++) {
            $this->assertEquals($i, $aggregator->capacity());
            $aggregator->addCandleEmitter(new CandleSourceMock("first_mock", [
                new Candle("first_mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            ]));
        }
    }

    /**
     * @expectedException \CoinCorp\RateAnalyzer\Exceptions\ClosedCandleEmitterException
     */
    public function testAddingClosedCandleEmitter()
    {
        $logger = new Logger("test");
        $logger->pushHandler(new NullHandler());
        $aggregator = new CandleAggregator($logger);
        $aggregator->addCandleEmitter(new CandleSourceMock("first_mock", []));
    }

    public function testCandleSizeMultiplication()
    {
        $firstSource = new CandleSourceMock("first_mock", [
            new Candle("first_mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("first_mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
        ]);

        $secondSource = new CandleSourceMock("second_mock", [
            new Candle("second_mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("second_mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ]);

        $logger = new Logger("test");
        $logger->pushHandler(new NullHandler());
        $aggregator = new CandleAggregator($logger);
        $aggregator->addCandleEmitter($firstSource);
        $aggregator->addCandleEmitter($secondSource);

        foreach ($aggregator->rows() as $row) {
            $this->assertCount(2, $row->candles);
            $this->assertEquals($row->candles[0]->start->getTimestamp(), $row->candles[1]->start->getTimestamp());
        }
    }

    public function testPrevsNumberEqualsHistorySize()
    {
        $firstSource = new CandleSourceMock("first_mock", [
            new Candle("first_mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("first_mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("first_mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("first_mock", new DateTime("Sun, 11 Jun 2017 09:24:00 +0000"), 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("first_mock", new DateTime("Sun, 11 Jun 2017 09:25:00 +0000"), 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
        ]);

        $secondSource = new CandleSourceMock("second_mock", [
            new Candle("second_mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("second_mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("second_mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("second_mock", new DateTime("Sun, 11 Jun 2017 09:24:00 +0000"), 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("second_mock", new DateTime("Sun, 11 Jun 2017 09:25:00 +0000"), 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ]);

        $rows = 5;

        $logger = new Logger("test");
        $logger->pushHandler(new NullHandler());

        for ($historySize = 0; $historySize < $rows; $historySize++) {
            $aggregator = new CandleAggregator($logger, $historySize);
            $aggregator->addCandleEmitter($firstSource);
            $aggregator->addCandleEmitter($secondSource);

            $dataRow = null;
            foreach ($aggregator->rows() as $dataRow) {
            }

            $count = 0;
            while ($dataRow->prev !== null) {
                $count++;
                $dataRow = $dataRow->prev;
            }

            $this->assertEquals($historySize, $count);
        }
    }
}
