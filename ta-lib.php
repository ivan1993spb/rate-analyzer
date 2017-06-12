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
     * @return array
     * @link http://php.net/manual/en/function.trader-macd.php
     */
    function trader_macd($real, $fastPeriod, $slowPeriod, $signalPeriod)
    {
        return [];
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
