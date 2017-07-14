<?php

namespace CoinCorp\RateAnalyzer\Correlation;

use CoinCorp\RateAnalyzer\AggregatorInterface;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableADX;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableATR;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableCCI;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableClosePrice;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableCMO;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableEMA;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableMACD;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableMOM;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableRSI;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableSMA;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableTrades;
use CoinCorp\RateAnalyzer\Correlation\CandleVariables\CandleVariableVolume;
use Monolog\Logger;

/**
 * Class Correlation
 *
 * @package CoinCorp\RateAnalyzer
 */
class Correlation
{
    /**
     * @var integer
     */
    const TA_COMMON_CACHE_SIZE = 1500;

    /**
     * @var \CoinCorp\RateAnalyzer\AggregatorInterface
     */
    private $aggregator;

    /**
     * @var \Monolog\Logger
     */
    private $log;

    /**
     * @var boolean
     */
    private $extended;

    /**
     * Analyzer constructor.
     *
     * @param \CoinCorp\RateAnalyzer\AggregatorInterface $aggregator
     * @param \Monolog\Logger                            $log
     * @param bool                                       $extended
     */
    public function __construct(AggregatorInterface $aggregator, Logger $log, $extended = false)
    {
        $this->aggregator = $aggregator;
        $this->log = $log;
        $this->extended = $extended;
    }

    public function findCorrelation()
    {
        // Return if nothing to analyze
        if ($this->aggregator->capacity() === 0) {
            return;
        }

        /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         *                                   ИНИЦИАЛИЗАЦИЯ ПЕРЕМЕННЫХ                                    *
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

        $variablePerCandle = $this->extended ? 21 : 19;

        /** @var \CoinCorp\RateAnalyzer\Correlation\CandleVariableInterface[] $variables */
        $variables = [];

        // Для каждой свечи добавляем три типа переменных
        foreach ($this->aggregator->emittersNames() as $name) {
            array_push($variables, new CandleVariableClosePrice($name.'_close'));
            array_push($variables, new CandleVariableSMA($name.'_close_sma-10', 10));
            array_push($variables, new CandleVariableSMA($name.'_close_sma-35', 35));
            array_push($variables, new CandleVariableEMA($name.'_close_ema-9', 9, self::TA_COMMON_CACHE_SIZE));
            array_push($variables, new CandleVariableEMA($name.'_close_ema-30', 9, self::TA_COMMON_CACHE_SIZE));
            array_push($variables, new CandleVariableMACD($name.'_close_macd-5-35-5', 5, 35, 5, self::TA_COMMON_CACHE_SIZE));
            array_push($variables, new CandleVariableMACD($name.'_close_macd-12-26-9', 12, 26, 9, self::TA_COMMON_CACHE_SIZE));
            array_push($variables, new CandleVariableCCI($name.'_close_cci-10', 10));
            array_push($variables, new CandleVariableCCI($name.'_close_cci-30', 30));
            array_push($variables, new CandleVariableADX($name.'_close_adx-10', 10));
            array_push($variables, new CandleVariableADX($name.'_close_adx-30', 30));
            array_push($variables, new CandleVariableMOM($name.'_close_mom-10', 10));
            array_push($variables, new CandleVariableMOM($name.'_close_mom-30', 30));
            array_push($variables, new CandleVariableCMO($name.'_close_cmo-10', 10));
            array_push($variables, new CandleVariableCMO($name.'_close_cmo-30', 30));
            array_push($variables, new CandleVariableATR($name.'_close_atr-10', 10));
            array_push($variables, new CandleVariableATR($name.'_close_atr-30', 30));
            array_push($variables, new CandleVariableRSI($name.'_close_rsi-10', 10));
            array_push($variables, new CandleVariableRSI($name.'_close_rsi-30', 30));

            // TODO: Переопределить набор extended.
            if ($this->extended) {
                array_push($variables, new CandleVariableVolume($name.'_volume'));
                array_push($variables, new CandleVariableTrades($name.'_trades'));
            }
        }

        /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         *                                     ПОЛУЧЕНИЕ ГЕНЕРАТОРА                                      *
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

        $generator = $this->aggregator->rows();
        if (!$generator->valid()) {
            return;
        }

        /** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
        $dataRow = $generator->current();
        $this->log->info("Start of time range", ['time' => $dataRow->time]);

        /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         *                          СДВИГ ГЕНЕРАТОРА И ПОДГОТОВКА ПЕРЕМЕННЫХ                             *
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

        // Начинаем средние значения только тогда, когда все переменные готовы
        while (true) {
            /** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
            $dataRow = $generator->current();
            $generator->next();

            foreach ($dataRow->candles as $column => $candle) {
                // Для каждой переменной для свечи
                for ($i = 0; $i < $variablePerCandle; $i++) {
                    $variable_index = $variablePerCandle*$column + $i;
                    $variable = $variables[$variable_index];
                    $variable->update($candle);
                }
            }

            $ready = true;
            foreach ($variables as $variable) {
                if (!$variable->ready()) {
                    $ready = false;
                    break;
                }
            }

            if ($ready) {
                break;
            }

            if (!$generator->valid()) {
                return;
            }
        }

        /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         *                             ПОДСЧЕТ СРЕДНИХ ЗНАЧЕНИЙ ПЕРЕМЕННЫХ                               *
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

        if (!$generator->valid()) {
            return;
        }

        /** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
        $dataRow = $generator->current();
        $this->log->info("Start calculating mean variable values on", ['time' => $dataRow->time]);

        /** @var float[] $means */
        $means = array_fill(0, sizeof($variables), 0.0);

        $index = 0;

        while (true) {
            /** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
            $dataRow = $generator->current();

            $count = $index + 1;
            $k = ($count - 1)  / $count;

            // Для каждой свечи
            foreach ($dataRow->candles as $column => $candle) {
                // Для каждой переменной для свечи
                for ($i = 0; $i < $variablePerCandle; $i++) {
                    $variable_index = $variablePerCandle*$column + $i;
                    $variable = $variables[$variable_index];
                    $variable->update($candle);
                    $means[$variable_index] = $means[$variable_index] * $k  + $variable->value() / $count;
                }
            }

            $index++;
            $generator->next();
            if (!$generator->valid()) {
                break;
            }
        }

        foreach ($variables as $variable) {
            $variable->free();
        }

        /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         *                                     ПОЛУЧЕНИЕ ГЕНЕРАТОРА                                      *
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

        $generator = $this->aggregator->rows();
        if (!$generator->valid()) {
            return;
        }

        /** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
        $dataRow = $generator->current();
        $this->log->info("Start of time range", ['time' => $dataRow->time]);

        /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         *                          СДВИГ ГЕНЕРАТОРА И ПОДГОТОВКА ПЕРЕМЕННЫХ                             *
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

        // Начинаем считать корреляцию только тогда, когда все переменные готовы
        while (true) {
            /** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
            $dataRow = $generator->current();
            $generator->next();

            foreach ($dataRow->candles as $column => $candle) {
                // Для каждой переменной для свечи
                for ($i = 0; $i < $variablePerCandle; $i++) {
                    $variable_index = $variablePerCandle*$column + $i;
                    $variable = $variables[$variable_index];
                    $variable->update($candle);
                }
            }

            $ready = true;
            foreach ($variables as $variable) {
                if (!$variable->ready()) {
                    $ready = false;
                    break;
                }
            }

            if ($ready) {
                break;
            }

            if (!$generator->valid()) {
                return;
            }
        }

        /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         *                                       ПОДСЧЕТ ОТКЛОНЕНИЙ                                      *
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

        if (!$generator->valid()) {
            return;
        }

        /** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
        $dataRow = $generator->current();
        $this->log->info("Start calculating on", ['time' => $dataRow->time]);

        /** @var float[][] $covsXYZ */
        $covsXYZ = array_fill(0, sizeof($variables), array_fill(0, sizeof($variables), 0.0));

        /** @var float[] $varS */
        $varS = array_fill(0, sizeof($variables), 0.0);

        while (true) {
            /** @var \CoinCorp\RateAnalyzer\DataRow $dataRow */
            $dataRow = $generator->current();

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
                        $covsXYZ[$xi][$yi] += ($variableX->value() - $means[$xi]) * ($variableY->value() - $means[$yi]);
                    }
                }
            }

            foreach ($variables as $i => $variable) {
                $varS[$i] += pow($variable->value() - $means[$i], 2);
            }

            $generator->next();
            if (!$generator->valid()) {
                break;
            }
        }

        // Значения переменных больше не пригодяться, значит можно освободить память почистя кеши
        foreach ($variables as $variable) {
            $variable->free();
        }

        /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         *                                       ПОДСЧЕТ КОРРЕЛЯЦИИ                                      *
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

        $varS = array_map('sqrt', $varS);

        $this->log->info("means", $means);
        $this->log->info("varS", $varS);

        /** @var float[][] $R_XYZ */
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
                                'variable_name' => $variableX->name()
                            ]);
                        }
                        if ($sY == 0) {
                            $this->log->err("Division by zero! May be candle size should be increased.", [
                                'variable_name' => $variableY->name()
                            ]);
                        }

                        $R_XYZ[$xi][$yi] = null;
                    } else {
                        $R_XYZ[$xi][$yi] = round($covXY / ($sX * $sY), 3);
                    }
                }
            }
        }

        /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
         *                                        ВЫВОД РЕЗУЛЬТАТА                                       *
         * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

        $this->log->info("Write corr table to stdout");

        $names = [];
        foreach ($variables as $variable) {
            array_push($names, $variable->name());
        }

        // TODO: Возвращать значения.
        echo json_encode([
            'names' => $names,
            'corr'  => $R_XYZ,
        ]);
    }
}
