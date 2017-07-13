<?php

use CoinCorp\RateAnalyzer\Candle;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableClosePrice;
use PHPUnit\Framework\TestCase;

/**
 * Class CandleVariableClosePriceTest
 */
class CandleVariableClosePriceTest extends TestCase
{
    public function testCandleVariableHasValidValue()
    {
        $var = new CandleVariableClosePrice("name");
        $var->update(new Candle("label", new DateTime('now'), 60, 5, 15, 1, 10, 0.5, 10.5, 5));
        $this->assertEquals(10, $var->value());
    }
}
