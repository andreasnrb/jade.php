<?php

namespace Jade\Nodes;

class Text extends Node {
    public $val;

    public function __construct($line) {
        $this->val = is_string($line) ? $line : '';
    }
}
