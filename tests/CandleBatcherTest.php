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
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
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
}
