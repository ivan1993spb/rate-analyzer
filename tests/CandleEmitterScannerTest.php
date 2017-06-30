<?php

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\CandleSourceMock;
use CoinCorp\RateAnalyzer\Scanners\CandleEmitterScanner;
use CoinCorp\RateAnalyzer\TimeRange;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * Class CandleEmitterScannerTest
 */
class CandleEmitterScannerTest extends TestCase
{
    function testEmptyEmitterHasNoRanges()
    {
        $source = new CandleSourceMock("mock", []);

        $logger = new Logger('logger');
        $logger->pushHandler(new NullHandler());

        $scanner = new CandleEmitterScanner($source, $logger);
        $this->assertFalse($scanner->scan()->valid());
    }

    function testNotEmptyEmitterHasRanges()
    {
        $source = new CandleSourceMock("mock", [
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), 60, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), 60, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), 60, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ]);

        $logger = new Logger('logger');
        $logger->pushHandler(new NullHandler());

        $scanner = new CandleEmitterScanner($source, $logger);
        $this->assertTrue($scanner->scan()->valid());
    }

    function testScanTwoRanges()
    {
        $source = new CandleSourceMock("mock", [
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"), 60, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:21:00 +0000"), 60, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:22:00 +0000"), 60, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:23:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:28:00 +0000"), 60, 1.0, 2.0, 0.5, 0.2, 1.1, 9.5, 5),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:29:00 +0000"), 60, 0.2, 3.0, 0.1, 0.8, 1.1, 7.5, 6),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:30:00 +0000"), 60, 0.8, 1.0, 0.5, 0.3, 1.1, 3.5, 4),
            new Candle("mock", new DateTime("Sun, 11 Jun 2017 09:31:00 +0000"), 60, 0.3, 5.0, 0.2, 2.2, 1.1, 8.0, 7),
        ]);

        $logger = new Logger('logger');
        $logger->pushHandler(new NullHandler());

        $scanner = new CandleEmitterScanner($source, $logger);

        $generator = $scanner->scan();

        $this->assertTrue($generator->valid());
        $this->assertEquals(new TimeRange(
            new DateTime("Sun, 11 Jun 2017 09:20:00 +0000"),
            new DateTime("Sun, 11 Jun 2017 09:24:00 +0000")
        ), $generator->current());

        $generator->next();
        $this->assertTrue($generator->valid());

        $this->assertEquals(new TimeRange(
            new DateTime("Sun, 11 Jun 2017 09:28:00 +0000"),
            new DateTime("Sun, 11 Jun 2017 09:32:00 +0000")
        ), $generator->current());

        $generator->next();
        $this->assertFalse($generator->valid());
    }
}
