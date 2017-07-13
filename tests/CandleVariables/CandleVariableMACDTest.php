<?php

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableMACD;
use PHPUnit\Framework\TestCase;

/**
 * Class CandleVariableMACDTest
 */
class CandleVariableMACDTest extends TestCase
{
    public function testCandleVariableHasValidValue()
    {
        $var = new CandleVariableMACD("name", 5, 15, 7, 50);
        $var->update(new Candle("label", new DateTime('now'), 60, 5, 15, 1, 10, 0.5, 10.5, 5));
        $this->assertFalse($var->ready());
    }
}
