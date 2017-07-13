<?php

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableADX;
use PHPUnit\Framework\TestCase;

/**
 * Class CandleVariableADXTest
 */
class CandleVariableADXTest extends TestCase
{
    public function testCandleVariableHasValidValue()
    {
        $period = 100;
        $var = new CandleVariableADX("name", $period);
        for ($i = 0; $i < $period*2 - 1; $i++) {
            $var->update(new Candle("label", new DateTime('now'), 60, 5, 15, 1, 10, 0.5, 10.5, 5));
            $this->assertFalse($var->ready());
        }
        $var->update(new Candle("label", new DateTime('now'), 60, 5, 15, 1, 12, 0.5, 10.5, 5));
        $this->assertTrue($var->ready());
    }
}
