<?php

require 'vendor/autoload.php';

use CoinCorp\RateAnalyzer\CandleSource;
use CoinCorp\RateAnalyzer\CandleAggregator;

$aggregator = new CandleAggregator();
$aggregator->addCandleEmitter(new CandleSource("usdt-btc_poloniex", "data/01-15_04-17_usdt-btc_poloniex.db" , "candles_USDT_BTC"));
$aggregator->addCandleEmitter(new CandleSource("usdt-eth_poloniex", "data/01-15_04-17_usdt-eth_poloniex.db", "candles_USDT_ETH"));
$aggregator->addCandleEmitter(new CandleSource("btc-ltc_poloniex", "data/01-16_04-17_btc-ltc_poloniex.db", "candles_BTC_LTC"));
$aggregator->addCandleEmitter(new CandleSource("btc-eth_poloniex", "data/01-15_04-17_btc-eth_poloniex.db", "candles_BTC_ETH"));

print_r($aggregator->next(new \DateTime(date('r', 1439010660)), new \DateTime(date('r', 1439121660))));
