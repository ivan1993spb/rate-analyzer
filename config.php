<?php

require "vendor/autoload.php";

use CoinCorp\RateAnalyzer\CandleSource;

return [
    // Pair to trade
    "trade-pair-name" => "usdt-btc",

    // Pairs sources to observe
    "src" => [
        [
            new CandleSource("usdt-btc_poloniex", "data/01-15_04-17_usdt-btc_poloniex.db" , "table"),
            new CandleSource("usdt-eth_poloniex", "data/01-15_04-17_usdt-eth_poloniex.db", "table"),
            new CandleSource("btc-ltc_poloniex", "data/01-16_04-17_btc-ltc_poloniex.db", "table"),
            new CandleSource("btc-eth_poloniex", "data/01-15_04-17_btc-eth_poloniex.db", "table"),
        ]
    ]
];
