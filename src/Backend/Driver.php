<?php

namespace MageStack\Queue\Backend;

interface Driver
{
    public function __construct($config);
    public function query($query);
    public function exec($query);
}
