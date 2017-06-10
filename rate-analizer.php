<?php

require 'vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleSource;
use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleAggregator;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$aggregator = new CandleAggregator(new Logger('name'));
$aggregator->addCandleEmitter(new CandleSource(
    "btc-eth_poloniex", "data/01-15_04-17_btc-eth_poloniex.db",
    "candles_BTC_ETH",
    new \DateTime(date('r', 1451606700)),
    new \DateTime(date('r', 1451606880))
));

$aggregator->addCandleEmitter(new CandleSource(
    "btc-ltc_poloniex", "data/01-16_04-17_btc-ltc_poloniex.db",
    "candles_BTC_LTC",
    new \DateTime(date('r', 1451606700)),
    new \DateTime(date('r', 1451606820))
));


//$cb = new CandleBatcher($cs, 2);
//$cb2 = new CandleBatcher($cb, 2);

foreach ($aggregator->rows() as $row) {
    print_r($row);
}
