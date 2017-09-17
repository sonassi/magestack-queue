<?php

namespace MageStack\Queue\Backend;

use SQLite3;

class SQLite implements Driver
{
    private static $instance;

    public function __construct($dbPath)
    {
        self::$instance = new SQLite3($dbPath);
    }

    public function query($query)
    {
        return self::$instance->query($query);
    }

    public function exec($query)
    {
        return self::$instance->exec($query);
    }

    public function fetchArray($result)
    {
        return self::$instance->fetchArray($result);
    }
}
