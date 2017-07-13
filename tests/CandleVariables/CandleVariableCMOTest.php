<?php

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableCMO;
use PHPUnit\Framework\TestCase;

/**
 * Class CandleVariableCMOTest
 */
class CandleVariableCMOTest extends TestCase
{
    public function testCandleVariableHasValidValue()
    {
        $period = 100;
        $var = new CandleVariableCMO("name", $period);
        for ($i = 0; $i < $period; $i++) {
            $var->update(new Candle("label", new DateTime('now'), 60, 5, 15, 1, 10, 0.5, 10.5, 5));
            $this->assertFalse($var->ready());
        }
        $var->update(new Candle("label", new DateTime('now'), 60, 5, 15, 1, 12, 0.5, 10.5, 5));
        $this->assertTrue($var->ready());
    }
}
