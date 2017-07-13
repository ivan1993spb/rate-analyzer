<?php

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableRSI;
use PHPUnit\Framework\TestCase;

/**
 * Class CandleVariableRSITest
 */
class CandleVariableRSITest extends TestCase
{
    public function testCandleVariableHasValidValue()
    {
        $period = 100;
        $var = new CandleVariableRSI("name", $period);
        for ($i = 0; $i < $period; $i++) {
            $var->update(new Candle("label", new DateTime('now'), 60, 5, 15, 1, 4, 0.5, 10.5, 5));
        }
        $this->assertFalse($var->ready());
        $var->update(new Candle("label", new DateTime('now'), 60, 4, 15, 1, 8, 0.5, 10.5, 5));
        $this->assertTrue($var->ready());
    }
}
