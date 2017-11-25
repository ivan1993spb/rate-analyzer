# rate-analyzer

## Install

You need docker

1. Install dependencies: `make install`
2. Test: `make test`
3. Build image `make build`

## Commands

### Install short commands

```bash
alias cc-candles-to-json="docker run --rm -v \$(pwd):\$(pwd) -w \$(pwd) registry.gitlab.com/coincorp/rate-analyzer:latest /app/bin/candles-to-json.php"
alias cc-correlation="docker run --rm -v \$(pwd):\$(pwd) -w \$(pwd) registry.gitlab.com/coincorp/rate-analyzer:latest /app/bin/correlation.php"
alias cc-last-candles="docker run --rm -v \$(pwd):\$(pwd) -w \$(pwd) registry.gitlab.com/coincorp/rate-analyzer:latest /app/bin/last-candles.php"
alias cc-latest-prices="docker run --rm -v \$(pwd):\$(pwd) -w \$(pwd) registry.gitlab.com/coincorp/rate-analyzer:latest /app/bin/print-latest-prices.php"
alias cc-scan-candle-emitter="docker run --rm -v \$(pwd):\$(pwd) -w \$(pwd) registry.gitlab.com/coincorp/rate-analyzer:latest /app/bin/scan-candle-emitter.php"
alias cc-stats-trade-pairs="docker run --rm -v \$(pwd):\$(pwd) -w \$(pwd) registry.gitlab.com/coincorp/rate-analyzer:latest /app/bin/trade-pair-of-pairs.php"
alias cc-calc-indicators-stats="docker run --rm -v \$(pwd):\$(pwd) -w \$(pwd) registry.gitlab.com/coincorp/rate-analyzer:latest /app/bin/calc-indicators-stats.php"
```

### Command descriptions

* `cc-candles-to-json` - сделать срез графиков в json файл;
* `cc-correlation` - сгенерировать json файл с коэффециентами корреляции;
* `cc-last-candles` - последние свечи по всем валютным парам;
* `cc-latest-prices` - последние цены;
* `cc-scan-candle-emitter` - получить файл со всеми промежутками данных, которые есть в БД по всем валютным парам;
* `cc-stats-trade-pairs` - генерация статистики для парного трейдинга;
* `cc-calc-indicators-stats` - генерация статистики по парам;
