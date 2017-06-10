
## TODO

- Написать README.md
- Автоматизировать выгрузку БД со свечами
- Добавить Makefile
- Добавить в аггрегатор разные метрики

## Example

### CandleBatcher

```php
$cs = new CandleSource(
    "btc-eth_poloniex", "data/01-15_04-17_btc-eth_poloniex.db",
    "candles_BTC_ETH",
    new \DateTime(date('r', 1451606700), new DateTimeZone("UTC")),
    new \DateTime(date('r', 1451606880), new DateTimeZone("UTC"))
);

$cb = new CandleBatcher($cs, 2);

foreach ($cb->candles() as $candle) {
    print_r($candle);
}
```

### CandleAggregator

```php
$aggregator = new CandleAggregator(new Logger('name'));
$aggregator->addCandleEmitter(new CandleSource(
    "btc-eth_poloniex", "data/01-15_04-17_btc-eth_poloniex.db",
    "candles_BTC_ETH",
    new \DateTime(date('r', 1451606760), new DateTimeZone("UTC")),
    new \DateTime(date('r', 1451606880), new DateTimeZone("UTC"))
));
$aggregator->addCandleEmitter(new CandleSource(
    "btc-ltc_poloniex", "data/01-16_04-17_btc-ltc_poloniex.db",
    "candles_BTC_LTC",
    new \DateTime(date('r', 1451606700), new DateTimeZone("UTC")),
    new \DateTime(date('r', 1451606820), new DateTimeZone("UTC"))
));

foreach ($aggregator->rows() as $row) {
    print_r($row);
}
```
