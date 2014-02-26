<?php

namespace Jade\Nodes;

class Doctype extends Node {
    public $value;
    public $val;

    public function __construct($value) {
        $this->value = $value;
    }
}
