<?php

require "vendor/autoload.php";

use CoinCorp\RateAnalyzer\CandleSource;

return [
    // Pair to trade
    "trade-pair-name" => "usdt-btc",

    // Candle size
    "candle-size" => 2000,

    // Pairs sources to observe
    "sources" => [
        new CandleSource("btc-xmr_poloniex", "data/01-16_04-17_btc-xmr_poloniex.db", "candles_BTC_XMR",
            new DateTime(date('r', 1451606700), new DateTimeZone("UTC"))),
        new CandleSource("btc-ltc_poloniex", "data/01-16_04-17_btc-ltc_poloniex.db", "candles_BTC_LTC",
            new DateTime(date('r', 1451606700), new DateTimeZone("UTC"))),
        new CandleSource("usdt-btc_poloniex", "data/01-15_04-17_usdt-btc_poloniex.db", "candles_USDT_BTC",
            new DateTime(date('r', 1451606700), new DateTimeZone("UTC"))),
    ],

    // Сколько рядов свечей максимум может содержать кеш аггрегатора
    "cache-size" => 1000,
];
