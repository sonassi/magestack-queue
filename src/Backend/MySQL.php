<?php

namespace MageStack\Queue\Backend;

use mysqli;
use mysqli_result;

class MySQL implements Driver
{

    private static $instance;

    public function __construct($config)
    {
        self::$instance = new mysqli($config['host'], $config['user'], $config['password'], $config['name']);

        if (self::$instance->connect_error) {
            printf("Database connection failed: %s\n");
            exit();
        }
    }

    public function query($query)
    {
        self::$instance->real_query($query);
        return new MySQL_Result(self::$instance);
    }

    public function exec($query)
    {
        if (!($result = self::$instance->query($query)))
            printf("Error: %s\n", self::$instance->error);

        return $result;
    }
}

/*
 * This class purely exists to provide direct compatbility
 * with the SQLite method fetchArray()
 */
class MySQL_Result extends mysqli_result
{
    public function fetchArray()
    {
        if ($row = $this->fetch_assoc())
            $row = array_merge($row, array_values($row));
        return $row;
    }
}