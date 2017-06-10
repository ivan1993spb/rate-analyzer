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
    const MIN_CANDLE_SIZE = 60;

    const CHUNK_SIZE = 1000;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $path;

    /**
     * @var \Illuminate\Database\Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $table;

    /**
     * @var \DateTime
     */
    private $from;

    /**
     * @var \DateTime
     */
    private $to;

    /**
     * CandleSource constructor.
     *
     * @param string    $name
     * @param string    $path
     * @param string    $table
     * @param \DateTime $from
     * @param \DateTime $to
     * @throws CandleSourceFileNotFoundException
     */
    public function __construct($name, $path, $table, DateTime $from, DateTime $to)
    {
        $this->name = $name;
        $this->path = $path;
        if (!is_file($path)) {
            throw new CandleSourceFileNotFoundException();
        }
        $this->connection = new Connection(new PDO('sqlite:'.$path));
        $this->table = $table;
        $this->from = $from;
        $this->to = $to;
    }

    public function __destruct()
    {
        $this->connection->disconnect();
    }

    /**
     * @return \Generator|\CoinCorp\RateAnalyzer\Candle[]
     */
    public function candles()
    {
        $offset = 0;
        $limit = self::CHUNK_SIZE;

        while (true) {
            /** @var \CoinCorp\RateAnalyzer\Candle[] $candles */
            $candles = [];

            $callback = function ($raw) use (&$candles) {
                array_push($candles, Candle::fromArray($this->name, (array)$raw));
            };

            $this->connection
                ->table($this->table)
                ->whereBetween('start', [$this->from->getTimestamp(), $this->to->getTimestamp()])
                ->orderBy('start')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->each($callback);

            if (count($candles) > 0) {
                foreach ($candles as $candle) {
                    yield $candle;
                }

                $offset += $limit;
            } else {
                break;
            }
        }
    }

    /**
     * Returns candle size in seconds
     *
     * @return integer
     */
    public function getCandleSize()
    {
        return self::MIN_CANDLE_SIZE;
    }
}
