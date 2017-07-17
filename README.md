# rate-analyzer

## Install

You need docker

1. Install dependencies: `make install`
2. Test: `make test`
3. Build image `make build`

## Установка коротких комманд

```bash
alias cc-candles-to-json="docker run --rm -v \$(pwd):/workdir -w /workdir registry.gitlab.com/coincorp/rate-analyzer:latest /app/bin/candles-to-json.php"
alias cc-correlation="docker run --rm -v \$(pwd):/workdir -w /workdir registry.gitlab.com/coincorp/rate-analyzer:latest /app/bin/correlation.php"
alias cc-last-candles="docker run --rm -v \$(pwd):/workdir -w /workdir registry.gitlab.com/coincorp/rate-analyzer:latest /app/bin/last-candles.php"
alias cc-latest-prices="docker run --rm -v \$(pwd):/workdir -w /workdir registry.gitlab.com/coincorp/rate-analyzer:latest /app/bin/print-latest-prices.php"
alias cc-scan-candle-emitter="docker run --rm -v \$(pwd):/workdir -w /workdir registry.gitlab.com/coincorp/rate-analyzer:latest /app/bin/scan-candle-emitter.php"
```

`cc-candles-to-json` – извлечь свечи из БД в json файл

`cc-correlation' – json файл с корреляцией (все от всех)

`cc-last-candles` – последние свечи по всем валютным парам

`cc-latest-prices` – последние цены

`cc-scan-candle-emitter` – получить html файл со всеми промежутками (и разрывами) данных, которые есть в БД по всем валютным парам