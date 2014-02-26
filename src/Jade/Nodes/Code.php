<?php

namespace Jade\Nodes;

class Code extends Node {
    public $buffer;
    public $escape;
    public $val;
    public $block;

    public function __construct($value,$buffer,$escape) {
        $this->val = $value;
        $this->buffer = $buffer;
        $this->escape = $escape;
    }
}
