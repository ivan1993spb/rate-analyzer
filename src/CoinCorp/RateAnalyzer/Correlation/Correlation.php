<?php

namespace CoinCorp\RateAnalyzer\Correlation;

use CoinCorp\RateAnalyzer\AggregatorInterface;
use CoinCorp\RateAnalyzer\Candle;
use Monolog\Logger;
use PHPExcel;
use PHPExcel_Cell;
use PHPExcel_IOFactory;
use PHPExcel_Style_Fill;

/**
 * Class Correlation
 *
 * @package CoinCorp\RateAnalyzer
 */
class Correlation
{
    /**
     * Невычисляемые поля в excel пометить следующим знаком
     *
     * @var string
     */
    const EXCEL_NAN_MARK = "NaN =(";

    /**
     * @var \CoinCorp\RateAnalyzer\AggregatorInterface
     */
    private $aggregator;

    /**
     * @var \Monolog\Logger
     */
    private $log;

    /**
     * @var string
     */
    private $XLSXFile;

    /**
     * @var boolean
     */
    private $extended;

    /**
     * Analyzer constructor.
     *
     * @param \CoinCorp\RateAnalyzer\AggregatorInterface $aggregator
     * @param \Monolog\Logger                            $log
     * @param string                                     $XLSXFile
     * @param bool                                       $extended
     */
    public function __construct(AggregatorInterface $aggregator, Logger $log, $XLSXFile, $extended = false)
    {
        $this->aggregator = $aggregator;
        $this->log = $log;
        $this->XLSXFile = $XLSXFile;
        $this->extended = $extended;
    }

    public function findCorrelation()
    {
        $variablePerCandle = $this->extended ? 23 : 21;

        /** @var \CoinCorp\RateAnalyzer\Correlation\CandleVariable[] $variables */
        $variables = [];

        // Для каждой свечи добавляем три типа переменных
        foreach ($this->aggregator->emittersNames() as $name) {
            array_push(
                $variables,
                new CandleVariable($name.'_close', function(Candle $candle) {
                    return $candle->close;
                }),
                new CandleVariable($name.'_close_sma-8', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 12) {
                        array_shift($cache);
                    }

                    $SMA = trader_sma($cache, 8);
                    if ($SMA === false) {
                        return 0;
                    }
                    $arr = array_values($SMA);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_sma-15', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 20) {
                        array_shift($cache);
                    }

                    $SMA = trader_sma($cache, 15);
                    if ($SMA === false) {
                        return 0;
                    }
                    $arr = array_values($SMA);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_ema-8', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 12) {
                        array_shift($cache);
                    }

                    $EMA = trader_ema($cache, 8);
                    if ($EMA === false) {
                        return 0;
                    }
                    $arr = array_values($EMA);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_ema-15', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 20) {
                        array_shift($cache);
                    }

                    $EMA = trader_ema($cache, 15);
                    if ($EMA === false) {
                        return 0;
                    }
                    $arr = array_values($EMA);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_ema-30', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 50) {
                        array_shift($cache);
                    }

                    $EMA = trader_ema($cache, 30);
                    if ($EMA === false) {
                        return 0;
                    }
                    $arr = array_values($EMA);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_macd-10-21-9', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 30) {
                        array_shift($cache);
                    }

                    $MACD = trader_macd($cache, 10, 21, 9);
                    if ($MACD === false) {
                        return 0;
                    }
                    $arr = array_values($MACD[0]);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_macd-15-31-19', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 65) {
                        array_shift($cache);
                    }

                    $MACD = trader_macd($cache, 15, 31, 19);
                    if ($MACD === false) {
                        return 0;
                    }
                    $arr = array_values($MACD[0]);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_cci-12', function(Candle $candle) {
                    static $cacheHigh = [];
                    static $cacheLow = [];
                    static $cacheClose = [];

                    array_push($cacheHigh, $candle->high);
                    while (sizeof($cacheHigh) > 15) {
                        array_shift($cacheHigh);
                    }
                    array_push($cacheLow, $candle->low);
                    while (sizeof($cacheLow) > 15) {
                        array_shift($cacheLow);
                    }
                    array_push($cacheClose, $candle->close);
                    while (sizeof($cacheClose) > 15) {
                        array_shift($cacheClose);
                    }

                    $CCI = trader_cci($cacheHigh, $cacheLow, $cacheClose, 12);
                    if ($CCI === false) {
                        return 0;
                    }
                    $arr = array_values($CCI);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_cci-25', function(Candle $candle) {
                    static $cacheHigh = [];
                    static $cacheLow = [];
                    static $cacheClose = [];

                    array_push($cacheHigh, $candle->high);
                    while (sizeof($cacheHigh) > 35) {
                        array_shift($cacheHigh);
                    }
                    array_push($cacheLow, $candle->low);
                    while (sizeof($cacheLow) > 35) {
                        array_shift($cacheLow);
                    }
                    array_push($cacheClose, $candle->close);
                    while (sizeof($cacheClose) > 35) {
                        array_shift($cacheClose);
                    }

                    $CCI = trader_cci($cacheHigh, $cacheLow, $cacheClose, 25);
                    if ($CCI === false) {
                        return 0;
                    }
                    $arr = array_values($CCI);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_adx-12', function(Candle $candle) {
                    static $cacheHigh = [];
                    static $cacheLow = [];
                    static $cacheClose = [];

                    array_push($cacheHigh, $candle->high);
                    while (sizeof($cacheHigh) > 35) {
                        array_shift($cacheHigh);
                    }
                    array_push($cacheLow, $candle->low);
                    while (sizeof($cacheLow) > 35) {
                        array_shift($cacheLow);
                    }
                    array_push($cacheClose, $candle->close);
                    while (sizeof($cacheClose) > 35) {
                        array_shift($cacheClose);
                    }

                    $ADX = trader_adx($cacheHigh, $cacheLow, $cacheClose, 12);
                    if ($ADX === false) {
                        return 0;
                    }
                    $arr = array_values($ADX);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_adx-25', function(Candle $candle) {
                    static $cacheHigh = [];
                    static $cacheLow = [];
                    static $cacheClose = [];

                    array_push($cacheHigh, $candle->high);
                    while (sizeof($cacheHigh) > 50) {
                        array_shift($cacheHigh);
                    }
                    array_push($cacheLow, $candle->low);
                    while (sizeof($cacheLow) > 50) {
                        array_shift($cacheLow);
                    }
                    array_push($cacheClose, $candle->close);
                    while (sizeof($cacheClose) > 50) {
                        array_shift($cacheClose);
                    }

                    $ADX = trader_adx($cacheHigh, $cacheLow, $cacheClose, 25);
                    if ($ADX === false) {
                        return 0;
                    }
                    $arr = array_values($ADX);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_mom-12', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 15) {
                        array_shift($cache);
                    }

                    $MOM = trader_mom($cache, 12);
                    if ($MOM === false) {
                        return 0;
                    }
                    $arr = array_values($MOM);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_mom-25', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 35) {
                        array_shift($cache);
                    }

                    $MOM = trader_mom($cache, 25);
                    if ($MOM === false) {
                        return 0;
                    }
                    $arr = array_values($MOM);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_cmo-12', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 15) {
                        array_shift($cache);
                    }

                    $CMO = trader_cmo($cache, 12);
                    if ($CMO === false) {
                        return 0;
                    }
                    $arr = array_values($CMO);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_cmo-25', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 35) {
                        array_shift($cache);
                    }

                    $CMO = trader_cmo($cache, 25);
                    if ($CMO === false) {
                        return 0;
                    }
                    $arr = array_values($CMO);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_atr-12', function(Candle $candle) {
                    static $cacheHigh = [];
                    static $cacheLow = [];
                    static $cacheClose = [];

                    array_push($cacheHigh, $candle->high);
                    while (sizeof($cacheHigh) > 35) {
                        array_shift($cacheHigh);
                    }
                    array_push($cacheLow, $candle->low);
                    while (sizeof($cacheLow) > 35) {
                        array_shift($cacheLow);
                    }
                    array_push($cacheClose, $candle->close);
                    while (sizeof($cacheClose) > 35) {
                        array_shift($cacheClose);
                    }

                    $ATR = trader_atr($cacheHigh, $cacheLow, $cacheClose, 12);
                    if ($ATR === false) {
                        return 0;
                    }
                    $arr = array_values($ATR);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_atr-25', function(Candle $candle) {
                    static $cacheHigh = [];
                    static $cacheLow = [];
                    static $cacheClose = [];

                    array_push($cacheHigh, $candle->high);
                    while (sizeof($cacheHigh) > 50) {
                        array_shift($cacheHigh);
                    }
                    array_push($cacheLow, $candle->low);
                    while (sizeof($cacheLow) > 50) {
                        array_shift($cacheLow);
                    }
                    array_push($cacheClose, $candle->close);
                    while (sizeof($cacheClose) > 50) {
                        array_shift($cacheClose);
                    }

                    $ATR = trader_atr($cacheHigh, $cacheLow, $cacheClose, 25);
                    if ($ATR === false) {
                        return 0;
                    }
                    $arr = array_values($ATR);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_rsi-8', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 12) {
                        array_shift($cache);
                    }

                    $RSI = trader_rsi($cache, 8);
                    if ($RSI === false) {
                        return 0;
                    }
                    $arr = array_values($RSI);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_rsi-15', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 20) {
                        array_shift($cache);
                    }

                    $RSI = trader_rsi($cache, 15);
                    if ($RSI === false) {
                        return 0;
                    }
                    $arr = array_values($RSI);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                }),
                new CandleVariable($name.'_close_rsi-30', function(Candle $candle) {
                    static $cache = [];

                    array_push($cache, $candle->close);
                    while (sizeof($cache) > 50) {
                        array_shift($cache);
                    }

                    $RSI = trader_rsi($cache, 30);
                    if ($RSI === false) {
                        return 0;
                    }
                    $arr = array_values($RSI);
                    if (empty($arr)) {
                        return 0;
                    }

                    return $arr[sizeof($arr)-1];
                })
            );

            if ($this->extended) {
                array_push(
                    $variables,
                    new CandleVariable($name.'_volume', function(Candle $candle) {
                        return $candle->volume;
                    }),
                    new CandleVariable($name.'_trades', function(Candle $candle) {
                        return $candle->trades;
                    })
                );
            }
        }

        // Return if nothing to analyze
        if ($this->aggregator->capacity() === 0) {
            return;
        }

        /** @var float[] $means */
        $means = array_fill(0, sizeof($variables), 0.0);

        // Вычисление средних значений
        foreach ($this->aggregator->rows() as $index => $dataRow) {
            $count = $index + 1;
            $k = ($count - 1)  / $count;

            // Для каждой свечи
            foreach ($dataRow->candles as $column => $candle) {
                // Для каждой переменной для свечи
                for ($i = 0; $i < $variablePerCandle; $i++) {
                    $variable_index = $variablePerCandle*$column + $i;
                    $variable = $variables[$variable_index];
                    $variable->update($candle);
                    $means[$variable_index] = $means[$variable_index] * $k  + $variable->value / $count;
                }
            }
        }

        /** @var float[][] $covsXYZ */
        $covsXYZ = array_fill(0, sizeof($variables), array_fill(0, sizeof($variables), 0.0));

        /** @var float[] $varS */
        $varS = array_fill(0, sizeof($variables), 0.0);

        foreach ($this->aggregator->rows() as $index => $dataRow) {
            foreach ($dataRow->candles as $column => $candle) {
                // Для каждой переменной для свечи
                for ($i = 0; $i < $variablePerCandle; $i++) {
                    $variable_index = $variablePerCandle*$column + $i;
                    $variable = $variables[$variable_index];
                    $variable->update($candle);
                }
            }

            foreach ($variables as $xi => $variableX) {
                foreach ($variables as $yi => $variableY) {
                    if ($xi !== $yi) {
                        $covsXYZ[$xi][$yi] += ($variableX->value - $means[$xi]) * ($variableY->value - $means[$yi]);
                    }
                }
            }

            foreach ($variables as $i => $variable) {
                $varS[$i] += pow($variable->value - $means[$i], 2);
            }
        }

        $varS = array_map('sqrt', $varS);

        $this->log->info("means", $means);
        $this->log->info("varS", $varS);

        $R_XYZ = array_fill(0, sizeof($variables), array_fill(0, sizeof($variables), 0.0));

        foreach ($variables as $xi => $variableX) {
            foreach ($variables as $yi => $variableY) {
                if ($xi !== $yi) {
                    $covXY = $covsXYZ[$xi][$yi];
                    $sX = $varS[$xi];
                    $sY = $varS[$yi];
                    if ($sX * $sY == 0) {
                        // Данная ошибка может возникать из-за слишком маленьких значений цен и может быть преодолена
                        // значительным увеличением размера свечи!
                        if ($sX == 0) {
                            $this->log->err("Division by zero! May be candle size should be increased.", [
                                'variable_name' => $variableX->name
                            ]);
                        }
                        if ($sY == 0) {
                            $this->log->err("Division by zero! May be candle size should be increased.", [
                                'variable_name' => $variableY->name
                            ]);
                        }
                        $R_XYZ[$xi][$yi] = false;
                    } else {
                        $R_XYZ[$xi][$yi] = $covXY / ($sX * $sY);
                    }
                }
            }
        }

        $this->log->info("R_XYZ count:", ['corr' => $R_XYZ]);

        $this->log->info("Excel generation");

        $ExcelPriceList = new PHPExcel();

        $ExcelPriceList->setActiveSheetIndex(0);


        foreach(range(0, sizeof($variables)) as $columnID) {
            $columnIndex = PHPExcel_Cell::stringFromColumnIndex($columnID);
            $ExcelPriceList->getActiveSheet()->getColumnDimension($columnIndex)->setAutoSize(true);
        }

        $column = 1;
        $row = 1;
        foreach ($variables as $xi => $variableX) {
            $columnIndex = PHPExcel_Cell::stringFromColumnIndex($column);
            $ExcelPriceList->getActiveSheet()->setCellValue($columnIndex.$row, $variableX->name);
            $column += 1;
        }

        $row += 1;
        $column = 0;

        // Вывод данных в excel

        foreach ($variables as $xi => $variableX) {
            $columnIndex = PHPExcel_Cell::stringFromColumnIndex($column);
            $ExcelPriceList->getActiveSheet()->setCellValue($columnIndex.$row, $variableX->name);

            $column += 1;

            foreach ($variables as $yi => $variableY) {
                $columnIndex = PHPExcel_Cell::stringFromColumnIndex($column);

                // Если одна и та же ячейка или если не было возможности посчитать корреляцию из-за деления на ноль, не выводим
                if ($xi !== $yi && $R_XYZ[$xi][$yi] !== false) {
                    $value = round($R_XYZ[$xi][$yi], 3);
                    $ExcelPriceList->getActiveSheet()->setCellValue($columnIndex.$row, $value);

                    if ($value < -0.8) {
                        $ExcelPriceList->getActiveSheet()->getStyle($columnIndex.$row)->applyFromArray(
                            [
                                'fill' => [
                                    'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                    'color' => array('rgb' => 'aa1f1f')
                                ]
                            ]
                        );
                    } elseif ($value < -0.6) {
                        $ExcelPriceList->getActiveSheet()->getStyle($columnIndex.$row)->applyFromArray(
                            [
                                'fill' => [
                                    'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                    'color' => array('rgb' => 'ee1f1f')
                                ]
                            ]
                        );
                    } elseif ($value < -0.4) {
                        $ExcelPriceList->getActiveSheet()->getStyle($columnIndex.$row)->applyFromArray(
                            [
                                'fill' => [
                                    'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                    'color' => array('rgb' => 'ff8888')
                                ]
                            ]
                        );
                    } elseif ($value > 0.8) {
                        $ExcelPriceList->getActiveSheet()->getStyle($columnIndex.$row)->applyFromArray(
                            [
                                'fill' => [
                                    'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                    'color' => array('rgb' => '1faa1f')
                                ]
                            ]
                        );
                    } elseif ($value > 0.6) {
                        $ExcelPriceList->getActiveSheet()->getStyle($columnIndex.$row)->applyFromArray(
                            [
                                'fill' => [
                                    'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                    'color' => array('rgb' => '1fee1f')
                                ]
                            ]
                        );
                    } elseif ($value > 0.4) {
                        $ExcelPriceList->getActiveSheet()->getStyle($columnIndex.$row)->applyFromArray(
                            [
                                'fill' => [
                                    'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                    'color' => array('rgb' => '88ff88')
                                ]
                            ]
                        );
                    }
                } elseif ($R_XYZ[$xi][$yi] === false) {
                    $ExcelPriceList->getActiveSheet()->setCellValue($columnIndex.$row, self::EXCEL_NAN_MARK);
                } else {
                    $ExcelPriceList->getActiveSheet()->getStyle($columnIndex.$row)->applyFromArray(
                        [
                            'fill' => [
                                'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                'color' => array('rgb' => '000000')
                            ]
                        ]
                    );
                }

                $column += 1;
            }

            $column = 0;
            $row += 1;
        }

        $ExcelPriceList->getActiveSheet()->freezePane("B2");
        $ExcelPriceList->getActiveSheet()->getStyle("1:1")->getFont()->setBold(true);
        $ExcelPriceList->getActiveSheet()->getStyle("A:A")->getFont()->setBold(true);

        $objWriter = PHPExcel_IOFactory::createWriter($ExcelPriceList, 'Excel2007');
        $objWriter->save($this->XLSXFile);
    }
}
