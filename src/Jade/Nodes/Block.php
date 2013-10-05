<?php

namespace Jade\Nodes;

class Block extends Node {
    public $isBlock = true;
    /**
     * @var Node[]|Block[] array
     */
    public $nodes = array();
    public $includeBlock;
    public $yield;

    public function __construct($node=null) {
        if ($node) $this->push($node);
    }

    public function replace($other) {
        $other->nodes = $this->nodes;
    }

    public function push($node) {
        return array_push($this->nodes,$node);
    }

    public function isEmpty() {
        return 0 == count($this->nodes);
    }

    public function unshift($node) {
        return array_unshift($this->nodes, $node);
    }

    public function includeBlock() {
        $ret = $this;

        foreach ($this->nodes as $node) {
            if (isset($node->yield)) return $node;
            elseif (isset($node->textOnly)) continue;
            elseif (isset($node->includeBlock)) $ret = $node->includeBlock();
            elseif (isset($node->block) && !$node->block->isEmpty()) $ret = $node->block->includeBlock();
            if (isset($ret->yield)) return $ret;
        }

        return $ret;
    }

}
