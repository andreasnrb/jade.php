<?php

namespace Jade\Nodes;

class Text extends Node {
    public $value;

    public function __construct($line) {
        $this->value = is_string($line) ? $line : '';
    }
}
