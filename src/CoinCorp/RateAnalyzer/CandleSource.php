<?php

namespace CoinCorp\RateAnalyzer;

use CoinCorp\RateAnalyzer\Exceptions\CandleSourceFileNotFoundException;
use DateTime;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
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
     * Number of candles in head of stream to skip
     *
     * @var integer
     */
    private $skipCandles = 0;

    /**
     * CandleSource constructor.
     *
     * @param string         $name
     * @param string         $path
     * @param string         $table
     * @param \DateTime|null $from
     * @param \DateTime|null $to
     * @throws CandleSourceFileNotFoundException
     */
    public function __construct($name, $path, $table, DateTime $from = null, DateTime $to = null)
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

            $query = new Builder($this->connection);
            $query->from($this->table)->offset($offset)->limit($limit)->orderBy('start');
            if (!empty($this->from) && !empty($this->to)) {
                $query->whereBetween('start', [$this->from->getTimestamp(), $this->to->getTimestamp()]);
            } elseif (!empty($this->from) && empty($this->to)) {
                $query->where('start', '>', $this->from->getTimestamp());
            } elseif (empty($this->from) && !empty($this->to)) {
                $query->where('start', '<', $this->to->getTimestamp());
            }
            $query->get()->each(function ($raw) use (&$candles) {
                array_push($candles, Candle::fromArray($this->name, (array)$raw));
            });

            if (count($candles) > 0) {
                foreach ($candles as $candle) {
                    if ($this->skipCandles > 0) {
                        $this->skipCandles--;
                        continue;
                    }
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

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param integer $seconds
     * @return void
     */
    public function skipSeconds($seconds)
    {
        $this->skipCandles = (integer)floor($seconds / self::MIN_CANDLE_SIZE);
    }
}
