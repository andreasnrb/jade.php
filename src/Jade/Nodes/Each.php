<?php

namespace Jade\Nodes;

class Each extends Node {
    public $obj;
    public $value;
    public $key;
    public $block;

    /**
     * @param string $obj
     * @param string $value
     * @param string $key
     * @param null|Block $block
     */
    function __construct($obj, $value, $key, $block=null) {
        $this->obj = $obj;
        $this->value = $value;
        $this->key = $key;
        $this->block = $block;
    }
}
