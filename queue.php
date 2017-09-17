<?php

// We deliberately do not use an autoloader to reduce overhead
require_once realpath(__DIR__) . '/src/Init.php';
require_once realpath(__DIR__) . '/src/Queue.php';
require_once realpath(__DIR__) . '/src/Backend/Driver.php';
require_once realpath(__DIR__) . '/src/Backend/SQLite.php';
require_once realpath(__DIR__) . '/src/Backend/MySQL.php';

use MageStack\Queue\Init;

$init = new Init;
