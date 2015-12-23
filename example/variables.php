<?php

namespace Jade;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Jade/Jade.php';

$jade = new Jade('/tmp', true);
$title = "Hello World";
$header = "this is append";
require $jade->cache('index.jade');