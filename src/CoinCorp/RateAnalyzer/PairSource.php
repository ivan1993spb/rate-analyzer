<?php

namespace CoinCorp\RateAnalyzer;

use CoinCorp\RateAnalyzer\Exceptions\PairSourceFileNotFoundException;
use Illuminate\Database\Connection;
use PDO;

/**
 * Class PairSource
 *
 * @package CoinCorp\RateAnalyzer
 */
class PairSource
{
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
    public $connection;

    /**
     * @var string
     */
    public $table;

    /**
     * PairSource constructor.
     *
     * @param string $name
     * @param string $path
     * @param string $table
     * @throws PairSourceFileNotFoundException
     */
    public function __construct($name, $path, $table)
    {
        $this->name = $name;
        $this->path = $path;
        if (!is_file($path)) {
            throw new PairSourceFileNotFoundException();
        }
        $this->connection = new Connection(new PDO('sqlite:'.$path));
        $this->table = $table;
    }

    public function __destruct()
    {
        $this->connection->disconnect();
    }
}
