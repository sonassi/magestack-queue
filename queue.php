<?php

// We deliberately do not use an autoloader to reduce overhead
require_once realpath(__DIR__) . '/src/Init.php';
require_once realpath(__DIR__) . '/src/Queue.php';

use MageStack\Queue\Init;

$init = new Init;
