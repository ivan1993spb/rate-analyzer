<?php

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableCCI;
use PHPUnit\Framework\TestCase;

/**
 * Class CandleVariableCCITest
 */
class CandleVariableCCITest extends TestCase
{
    public function testCandleVariableHasValidValue()
    {
        $var = new CandleVariableCCI("name", 2);
        $var->update(new Candle("label", new DateTime('now'), 60, 5, 15, 1, 10, 0.5, 10.5, 5));
        $this->assertFalse($var->ready());
        $var->update(new Candle("label", new DateTime('now'), 60, 5, 15, 1, 12, 0.5, 10.5, 5));
        $this->assertTrue($var->ready());
    }
}
