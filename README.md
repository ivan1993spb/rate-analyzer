
# rate-analyzer

## Install

You need docker

1. Install dependencies: `make install`
2. Test: `make test`
3. Build image `make build`

## Установка коротких комманд

```bash
alias cc-candles-to-json="docker run --rm -v $(pwd):/workdir -w /workdir rate-analyzer:latest /app/bin/candles-to-json.php"
alias cc-correlation="docker run --rm -v $(pwd):/workdir -w /workdir rate-analyzer:latest /app/bin/correlation.php"
#...
alias cc-scan-candle-emitter="docker run --rm -v $(pwd):/workdir -w /workdir rate-analyzer:latest /app/bin/scan-candle-emitter.php"
```

## TODO

- Добавить недостающие алиасы
