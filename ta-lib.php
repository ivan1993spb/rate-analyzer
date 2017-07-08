<?php

/*
 * Here defined list of used TA-Lib functions and constants from trader PHP-extension to prevent IDE warnings
 *
 * @see http://php.net/manual/en/trader.constants.php
 * @see http://php.net/manual/en/ref.trader.php
 */

if (!defined("TRADER_ERR_SUCCESS")) {
    define("TRADER_ERR_SUCCESS", 0);
}

if (!defined("TRADER_ERR_SUCCESS")) {
    define("TRADER_ERR_LIB_NOT_INITIALIZE", 1);
}

if (!defined("TRADER_ERR_SUCCESS")) {
    define("TRADER_ERR_BAD_PARAM", 2);
}

if (!function_exists("trader_macd")) {
    /**
     * @param array   $real
     * @param integer $fastPeriod
     * @param integer $slowPeriod
     * @param integer $signalPeriod
     * @return array|boolean
     * @link http://php.net/manual/en/function.trader-macd.php
     */
    function trader_macd($real, $fastPeriod, $slowPeriod, $signalPeriod)
    {
        return false;
    }
}

if (!function_exists("trader_errno")) {
    /**
     * @return integer
     * @link http://php.net/manual/en/function.trader-errno.php
     */
    function trader_errno()
    {
        return 0;
    }
}

if (!function_exists("trader_sma")) {
    /**
     * @param array   $real
     * @param integer $timePeriod
     * @return array|boolean
     * @link http://php.net/manual/en/function.trader-sma.php
     */
    function trader_sma($real, $timePeriod)
    {
        return false;
    }
}

if (!function_exists("trader_ema")) {
    /**
     * @param array   $real
     * @param integer $timePeriod
     * @return array|boolean
     * @link http://php.net/manual/en/function.trader-ema.php
     */
    function trader_ema($real, $timePeriod)
    {
        return false;
    }
}

if (!function_exists("trader_cci")) {
    /**
     * @param array   $high
     * @param array   $low
     * @param array   $close
     * @param integer $timePeriod
     * @return array|boolean
     * @link http://php.net/manual/en/function.trader-cci.php
     */
    function trader_cci($high, $low, $close, $timePeriod)
    {
        return false;
    }
}

if (!function_exists("trader_adx")) {
    /**
     * @param array   $high
     * @param array   $low
     * @param array   $close
     * @param integer $timePeriod
     * @return array|boolean
     * @link http://php.net/manual/en/function.trader-adx.php
     */
    function trader_adx($high, $low, $close, $timePeriod)
    {
        return false;
    }
}

if (!function_exists("trader_mom")) {
    /**
     * @param array   $real
     * @param integer $timePeriod
     * @return array|boolean
     * @link http://php.net/manual/en/function.trader-mom.php
     */
    function trader_mom($real, $timePeriod)
    {
        return false;
    }
}

if (!function_exists("trader_cmo")) {
    /**
     * @param array   $real
     * @param integer $timePeriod
     * @return array|boolean
     * @link http://php.net/manual/en/function.trader-cmo.php
     */
    function trader_cmo($real, $timePeriod)
    {
        return false;
    }
}

if (!function_exists("trader_atr")) {
    /**
     * @param array   $high
     * @param array   $low
     * @param array   $close
     * @param integer $timePeriod
     * @return array|boolean
     * @link http://php.net/manual/en/function.trader-atr.php
     */
    function trader_atr($high, $low, $close, $timePeriod)
    {
        return false;
    }
}

if (!function_exists("trader_rsi")) {
    /**
     * @param array   $real
     * @param integer $timePeriod
     * @return array|boolean
     * @link http://php.net/manual/en/function.trader-rsi.php
     */
    function trader_rsi($real, $timePeriod)
    {
        return false;
    }
}
