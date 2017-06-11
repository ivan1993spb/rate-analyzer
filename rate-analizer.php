<?php

require 'vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleSource;
use CoinCorp\RateAnalyzer\CandleBatcher;
use CoinCorp\RateAnalyzer\CandleAggregator;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$aggregator = new CandleAggregator(new Logger('name'));
$aggregator->addCandleEmitter(new CandleBatcher(new CandleSource(
    "btc-eth_poloniex", "data/01-15_04-17_btc-eth_poloniex.db",
    "candles_BTC_ETH"//,
//    new \DateTime(date('r', 1451606700), new DateTimeZone("UTC")),
//    new \DateTime(date('r', 1451606880), new DateTimeZone("UTC"))
), 10));

$aggregator->addCandleEmitter(new CandleBatcher(new CandleSource(
    "btc-ltc_poloniex", "data/01-16_04-17_btc-ltc_poloniex.db",
    "candles_BTC_LTC"//,
//    new \DateTime(date('r', 1451606700), new DateTimeZone("UTC")),
//    new \DateTime(date('r', 1451606820), new DateTimeZone("UTC"))
), 10));

$aggregator->addCandleEmitter(new CandleBatcher(new CandleSource(
    "usdt-btc_poloniex", "data/01-15_04-17_usdt-btc_poloniex.db",
    "candles_USDT_BTC"//,
//    new \DateTime(date('r', 1451606700), new DateTimeZone("UTC")),
//    new \DateTime(date('r', 1451606820), new DateTimeZone("UTC"))
), 10));
//exit(0);

//$cb = new CandleBatcher(new CandleSource("btc-ltc_poloniex", "data/01-16_04-17_btc-ltc_poloniex.db", "candles_BTC_LTC"), 2);

//foreach ($cb->candles() as $candle) {
//    sleep(1);
//    print_r($candle);
//}

foreach ($aggregator->rows() as $row) {
    sleep(1);
    print_r($row);
}
