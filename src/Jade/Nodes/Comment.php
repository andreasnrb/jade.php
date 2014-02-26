<?php

namespace Jade\Nodes;

class Comment extends Node {
    public $buffer;
    public $val;
    public $block;

    public function __construct($value, $buffer) {
        $this->val = $value;
        $this->buffer = $buffer;
    }
}
