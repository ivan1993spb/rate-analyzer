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
        $variablePerCandle = $this->extended ? 3 : 1;

        /** @var \CoinCorp\RateAnalyzer\Correlation\CandleVariable[] $variables */
        $variables = [];

        // Для каждой свечи добавляем три типа переменных
        foreach ($this->aggregator->emittersNames() as $name) {
            array_push(
                $variables,
                new CandleVariable($name.'_close', function(Candle $candle) {
                    return $candle->close;
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
                    $R_XYZ[$xi][$yi] = $covXY / ($sX * $sY);
                }
            }
        }

        $this->log->info("R_XYZ", $R_XYZ);

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

        // Данные

        foreach ($variables as $xi => $variableX) {
            $columnIndex = PHPExcel_Cell::stringFromColumnIndex($column);
            $ExcelPriceList->getActiveSheet()->setCellValue($columnIndex.$row, $variableX->name);

            $column += 1;

            foreach ($variables as $yi => $variableY) {
                if ($xi !== $yi) {
                    $columnIndex = PHPExcel_Cell::stringFromColumnIndex($column);
                    $ExcelPriceList->getActiveSheet()->setCellValue($columnIndex.$row, $R_XYZ[$xi][$yi]);

                    $absoluteValue = abs($R_XYZ[$xi][$yi]);

                    if ($absoluteValue > 0.5) {
                        $ExcelPriceList->getActiveSheet()->getStyle($columnIndex.$row)->applyFromArray(
                            [
                                'fill' => [
                                    'type' => PHPExcel_Style_Fill::FILL_SOLID,
                                    'color' => array('rgb' => '00ff00')
                                ]
                            ]
                        );
                    }
                }

                $column += 1;
            }

            $column = 0;
            $row += 1;
        }


        $objWriter = PHPExcel_IOFactory::createWriter($ExcelPriceList, 'Excel2007');
        $objWriter->save($this->XLSXFile);
    }
}
