<?php

require "vendor/autoload.php";

use CoinCorp\RateAnalyzer\PairSource;

return [
    // Pair to trade
    "trade-pair-name" => "usdt-btc",

    // Pairs sources to observe
    "src" => [
        [
            new PairSource("usdt-btc_poloniex", "data/01-15_04-17_usdt-btc_poloniex.db" , "table"),
            new PairSource("usdt-eth_poloniex", "data/01-15_04-17_usdt-eth_poloniex.db", "table"),
            new PairSource("btc-ltc_poloniex", "data/01-16_04-17_btc-ltc_poloniex.db", "table"),
            new PairSource("btc-eth_poloniex", "data/01-15_04-17_btc-eth_poloniex.db", "table"),
        ]
    ]
];
