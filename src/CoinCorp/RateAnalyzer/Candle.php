<?php

namespace CoinCorp\RateAnalyzer;

use DateTime;
use DateTimeZone;

/**
 * Class Candle
 *
 * @package CoinCorp\RateAnalyzer
 */
class Candle
{
    /**
     * Contains information about exchange, currency and asset
     *
     * @var string
     */
    public $label;

    /**
     * @var \DateTime
     */
    public $start;

    /**
     * Seconds
     *
     * @var integer
     */
    public $duration;

    /**
     * @var float
     */
    public $open;

    /**
     * @var float
     */
    public $high;

    /**
     * @var float
     */
    public $low;

    /**
     * @var float
     */
    public $close;

    /**
     * Volume weighted price
     *
     * @var float
     */
    public $vwp;

    /**
     * @var float
     */
    public $volume;

    /**
     * @var integer
     */
    public $trades;

    /**
     * Candle constructor.
     *
     * @param string    $label
     * @param \DateTime $start
     * @param integer   $duration Seconds
     * @param float     $open
     * @param float     $high
     * @param float     $low
     * @param float     $close
     * @param float     $vwp
     * @param float     $volume
     * @param integer   $trades
     */
    public function __construct($label, DateTime $start, $duration, $open, $high, $low, $close, $vwp, $volume, $trades)
    {
        $this->label = $label;
        $this->start = $start;
        $this->duration = $duration;
        $this->open = $open;
        $this->high = $high;
        $this->low = $low;
        $this->close = $close;
        $this->vwp = $vwp;
        $this->volume = $volume;
        $this->trades = $trades;
    }

    /**
     * @param string  $label
     * @param integer $duration
     * @param array   $arr
     * @return self
     */
    public static function fromArray($label, $duration, array $arr)
    {
        $start = new DateTime();
        $start->setTimezone(new DateTimeZone('UTC'));
        if (array_key_exists('start', $arr)) {
            if ($arr['start'] instanceof DateTime) {
                $start = $arr['start'];
            } else {
                $start->setTimestamp(intval($arr['start']));
            }
        }

        $open = array_key_exists('open', $arr) ? floatval($arr['open']) : 0.0;
        $high = array_key_exists('high', $arr) ? floatval($arr['high']) : 0.0;
        $low = array_key_exists('low', $arr) ? floatval($arr['low']) : 0.0;
        $close = array_key_exists('close', $arr) ? floatval($arr['close']) : 0.0;
        $vwp = array_key_exists('vwp', $arr) ? floatval($arr['vwp']) : 0.0;
        $volume = array_key_exists('volume', $arr) ? floatval($arr['volume']) : 0.0;
        $trades = array_key_exists('trades', $arr) ? intval($arr['trades']) : 0;

        return new self($label, $start, $duration, $open, $high, $low, $close, $vwp, $volume, $trades);
    }
}
