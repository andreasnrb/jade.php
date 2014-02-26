<?php

namespace Jade\Nodes;

class Each extends Node {
    public $obj;
    public $key;
    public $block;
    public $alternative;
    public $val;

    /**
     * @param string $obj
     * @param string $value
     * @param string $key
     * @param null|Block $block
     */
    function __construct($obj, $value, $key, $block=null) {
        $this->obj = $obj;
        $this->val = $value;
        $this->key = $key;
        $this->block = $block;
    }
}
