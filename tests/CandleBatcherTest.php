<?php

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleSourceMock;
use PHPUnit\Framework\TestCase;

/**
 * Class CandleBatcherTest
 */
class CandleBatcherTest extends TestCase
{
    /**
     * @expectedException \CoinCorp\RateAnalyzer\Exceptions\ZeroBatchSizeException
     */
    public function testCreateCandleBatcherWithZeroBatchSize()
    {
        new CandleBatcher(new CandleSourceMock("mock", []), 0);
    }

    public function testCandleSizeMultiplication()
    {
        $minCandleSize = 60;
        $batchedCandles = 2;
        $batchers = 10;

        /** @var \CoinCorp\RateAnalyzer\CandleEmitterInterface $emitter */
        $emitter = new CandleSourceMock("mock", [], $minCandleSize);

        for ($i = 1; $i <= $batchers; $i++) {
            $emitter = new CandleBatcher($emitter, $batchedCandles);
            $this->assertEquals($minCandleSize * pow($batchedCandles, $i), $emitter->getCandleSize(), "Invalid candle size");
        }
    }

    public function testSameBatchSizeReturnsSameCandles()
    {
        $candles = [
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), 60, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), 60, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), 60, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ];

        $source = new CandleSourceMock("mock", $candles);
        $batcher = new CandleBatcher($source, 1);

        $sourceGenerator = $source->candles();
        $batcherGenerator = $batcher->candles();

        $iterations = 0;
        while ($sourceGenerator->valid() && $batcherGenerator->valid()) {
            $this->assertEquals($sourceGenerator->current(), $batcherGenerator->current(), "Candles are not equal");
            $sourceGenerator->next();
            $batcherGenerator->next();
            $iterations++;
        }
        $this->assertEquals(count($candles), $iterations, "Invalid iteration number");
    }

    public function testBatcherGeneratesCandlesWithValidDuration()
    {
        $candles = [
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), 60, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), 60, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), 60, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ];

        $source = new CandleSourceMock("mock", $candles);
        $batcher = new CandleBatcher($source, 2);

        $batcherGenerator = $batcher->candles();

        $this->assertTrue($batcherGenerator->valid());

        foreach ($batcherGenerator as $candle) {
            $this->assertEquals($batcher->getCandleSize(), $candle->duration);
        }
    }

    public function testCandleMergeReturnsCandleWithValidSize()
    {
        $duration = 60;
        $candles = [
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), $duration, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), $duration, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), $duration, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), $duration, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ];

        $class = new ReflectionClass(CandleBatcher::class);
        $method = $class->getMethod('mergeCandles');
        $method->setAccessible(true);
        $batcher = new CandleBatcher(new CandleSourceMock("mock", []), 4);
        /** @var \CoinCorp\RateAnalyzer\Candle $candle */
        $candle = $method->invokeArgs($batcher, [$candles]);

        $this->assertEquals($duration * sizeof($candles), $candle->duration);
    }

    public function testCandleMergeReturnedCandleStartsWithValidTime()
    {
        $duration = 60;
        $startTime = new DateTime("Sun, 11 Jun 2017 09:20:00 +0000");
        $candles = [
            new Candle("mock", $startTime, $duration, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), $duration, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), $duration, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), $duration, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ];

        $class = new ReflectionClass(CandleBatcher::class);
        $method = $class->getMethod('mergeCandles');
        $method->setAccessible(true);
        $batcher = new CandleBatcher(new CandleSourceMock("mock", []), 4);
        /** @var \CoinCorp\RateAnalyzer\Candle $candle */
        $candle = $method->invokeArgs($batcher, [$candles]);

        $this->assertEquals($startTime, $candle->start);
    }

    public function testCandleMergeReturnedCandleHasValidOpen()
    {
        $duration = 60;

        $candles = [
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), $duration, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), $duration, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), $duration, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), $duration, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ];

        $class = new ReflectionClass(CandleBatcher::class);
        $method = $class->getMethod('mergeCandles');
        $method->setAccessible(true);
        $batcher = new CandleBatcher(new CandleSourceMock("mock", []), 4);
        /** @var \CoinCorp\RateAnalyzer\Candle $candle */
        $candle = $method->invokeArgs($batcher, [$candles]);

        $this->assertEquals(1.0, $candle->open);
    }

    public function testCandleMergeReturnedCandleHasValidClose()
    {
        $duration = 60;

        $candles = [
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), $duration, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), $duration, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), $duration, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), $duration, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ];

        $class = new ReflectionClass(CandleBatcher::class);
        $method = $class->getMethod('mergeCandles');
        $method->setAccessible(true);
        $batcher = new CandleBatcher(new CandleSourceMock("mock", []), 4);
        /** @var \CoinCorp\RateAnalyzer\Candle $candle */
        $candle = $method->invokeArgs($batcher, [$candles]);

        $this->assertEquals(2.2, $candle->close);
    }

    public function testCandleMergeReturnedCandleHasValidHigh()
    {
        $duration = 60;

        $candles = [
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), $duration, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), $duration, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), $duration, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), $duration, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ];

        $class = new ReflectionClass(CandleBatcher::class);
        $method = $class->getMethod('mergeCandles');
        $method->setAccessible(true);
        $batcher = new CandleBatcher(new CandleSourceMock("mock", []), 4);
        /** @var \CoinCorp\RateAnalyzer\Candle $candle */
        $candle = $method->invokeArgs($batcher, [$candles]);

        $this->assertEquals(5.0, $candle->high);
    }

    public function testCandleMergeReturnedCandleHasValidLow()
    {
        $duration = 60;

        $candles = [
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), $duration, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), $duration, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), $duration, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), $duration, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ];

        $class = new ReflectionClass(CandleBatcher::class);
        $method = $class->getMethod('mergeCandles');
        $method->setAccessible(true);
        $batcher = new CandleBatcher(new CandleSourceMock("mock", []), 4);
        /** @var \CoinCorp\RateAnalyzer\Candle $candle */
        $candle = $method->invokeArgs($batcher, [$candles]);

        $this->assertEquals(0.1, $candle->low);
    }

    public function testCandleMergeReturnedCandleHasValidVolume()
    {
        $duration = 60;

        $candles = [
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), $duration, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), $duration, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), $duration, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), $duration, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ];

        $class = new ReflectionClass(CandleBatcher::class);
        $method = $class->getMethod('mergeCandles');
        $method->setAccessible(true);
        $batcher = new CandleBatcher(new CandleSourceMock("mock", []), 4);
        /** @var \CoinCorp\RateAnalyzer\Candle $candle */
        $candle = $method->invokeArgs($batcher, [$candles]);

        $volume = 0;
        foreach ($candles as $c) {
            $volume += $c->volume;
        }

        $this->assertEquals($volume, $candle->volume);
    }

    public function testCandleMergeReturnedCandleHasValidCountOfTrades()
    {
        $duration = 60;

        $candles = [
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), $duration, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), $duration, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), $duration, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), $duration, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ];

        $class = new ReflectionClass(CandleBatcher::class);
        $method = $class->getMethod('mergeCandles');
        $method->setAccessible(true);
        $batcher = new CandleBatcher(new CandleSourceMock("mock", []), 4);
        /** @var \CoinCorp\RateAnalyzer\Candle $candle */
        $candle = $method->invokeArgs($batcher, [$candles]);

        $trades = 0;
        foreach ($candles as $c) {
            $trades += $c->trades;
        }

        $this->assertEquals($trades, $candle->trades);
    }

    public function testCandleMergeReturnedCandleHasValidVWP()
    {
        $this->markTestSkipped();
        // TODO: Implement test for VWP.
    }

    public function testBrokenCandleStream()
    {
        $candles = [
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), 60, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
//            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), 60, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), 60, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),

//            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), 60, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:24:00 +0000"), 60, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
//            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:25:00 +0000"), 60, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),

//            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:26:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
//            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:27:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:28:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),

            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:29:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:30:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:31:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),

//            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:32:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:33:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:34:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),

            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:35:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:36:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
//            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:37:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),

            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:38:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:39:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:40:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),

//            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:41:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:42:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
//            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:43:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ];

        $batcher = new CandleBatcher(new CandleSourceMock("mock", $candles), 3);

        $count = 0;
        foreach ($batcher->candles() as $candle) {
            $count++;
        }

        $this->assertEquals(8, $count);
    }
}
