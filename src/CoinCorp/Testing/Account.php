<?php

namespace CoinCorp\Testing;

/**
 * Class Account
 *
 * @package CoinCorp\Testing
 */
class Account
{
    /**
     * @var float
     */
    private $currency = 0.0;

    /**
     * @var float
     */
    private $asset = 0.0;

    /**
     * @var integer
     */
    private $trades = 0;

    /**
     * Account constructor.
     *
     * @param float $startCurrency
     * @param float $startAsset
     */
    public function __construct($startCurrency, $startAsset)
    {
        $this->currency = $startCurrency;
        $this->asset = $startAsset;
    }

    /**
     * @param float $currentPrice
     * @param float $fee
     */
    public function long($currentPrice, $fee)
    {
        if ($this->currency > 0) {
            $this->asset += $this->currency/$currentPrice - $this->currency*$fee/$currentPrice;
            $this->currency = 0;
            $this->trades += 1;
        }
    }

    /**
     * @param float $currentPrice
     * @param float $fee
     */
    public function short($currentPrice, $fee)
    {
        if ($this->asset > 0) {
            $this->currency += $this->asset*$currentPrice - $this->asset*$currentPrice*$fee;
            $this->asset = 0;
            $this->trades += 1;
        }
    }

    /**
     * @return float
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @return float
     */
    public function getAsset()
    {
        return $this->asset;
    }

    /**
     * @return integer
     */
    public function getTrades()
    {
        return $this->trades;
    }

    /**
     * @param float $currentPrice
     * @return float
     */
    public function getDeposit($currentPrice)
    {
        return $this->currency + $this->asset*$currentPrice;
    }
}
