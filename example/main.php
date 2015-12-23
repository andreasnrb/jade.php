<?php

namespace Jade;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Jade/Jade.php';

$jade = new Jade('/tmp', true);
$html = $jade->render('index.jade');
echo $html;