<?php

namespace CoinCorp\RateAnalyzer;

use CoinCorp\RateAnalyzer\Exceptions\CandleSourceFileNotFoundException;
use DateTime;
use Illuminate\Database\Connection;
use PDO;

/**
 * Class CandleSource
 *
 * @package CoinCorp\RateAnalyzer
 */
class CandleSource implements CandleEmitterInterface
{
    const CANDLE_SIZE_MINUTES = 1;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $path;

    /**
     * @var \Illuminate\Database\Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $table;

    /**
     * CandleSource constructor.
     *
     * @param string $name
     * @param string $path
     * @param string $table
     * @throws CandleSourceFileNotFoundException
     */
    public function __construct($name, $path, $table)
    {
        $this->name = $name;
        $this->path = $path;
        if (!is_file($path)) {
            throw new CandleSourceFileNotFoundException();
        }
        $this->connection = new Connection(new PDO('sqlite:'.$path));
        $this->table = $table;
    }

    public function __destruct()
    {
        $this->connection->disconnect();
    }

    /**
     * @param \DateTime $from
     * @param \DateTime $to
     * @return \CoinCorp\RateAnalyzer\Candle[]
     */
    public function getCandles(DateTime $from, DateTime $to)
    {
        /** @var \CoinCorp\RateAnalyzer\Candle[] $candles */
        $candles = [];

        $this->connection
            ->table($this->table)
            ->whereBetween('start', [$from->getTimestamp(), $to->getTimestamp()])
            ->orderBy('start')
            ->each(function($raw) use (&$candles) {
                array_push($candles, Candle::fromArray($this->name, (array)$raw));
            });

        return $candles;
    }

    /**
     * Returns candle size in seconds
     *
     * @return integer
     */
    public function getCandleSize()
    {
        return self::CANDLE_SIZE_MINUTES * 60;
    }
}
