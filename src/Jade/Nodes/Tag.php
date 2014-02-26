<?php

namespace Jade\Nodes;


class Tag extends Attributes {
    public $buffer;
    public $name;
    public $attributes;
    public $block;
    public $selfClosing = false;
    public $inline_tags = array(
     'a'
    ,'abbr'
    ,'acronym'
    ,'b'
    ,'br'
    ,'code'
    ,'em'
    ,'font'
    ,'i'
    ,'img'
    ,'ins'
    ,'kbd'
    ,'map'
    ,'samp'
    ,'small'
    ,'span'
    ,'strong'
    ,'sub'
    ,'sup'
    );
    public $code;

    public function __construct($name, $block=null) {
        $this->name = strtolower($name);
        $this->block = $block ? $block : new Block();
        $this->attributes = array();
    }

    public function isInline() {
        return in_array($this->name, $this->inline_tags);
    }

    /**
     * Check if this tag's contents can be inlined.  Used for pretty printing.
     * @return bool
     */
    public function canInline() {
        /**
         * @var Node[]|Tag[] $nodes
         */
        $nodes = $this->block->nodes;

        /**
         * @param Node|Tag $node
         * @return array|bool
         */
        $isInline = function($node)  use (&$isInline) {
            if ($node->isBlock) {
                foreach ($node->nodes as $n) {
                    if (!$isInline($n)) {
                        return false;
                    }
                }
                return true;
            }
            return $node->isText || (isset($node->isInline) && $node->isInline());
        };

        if (count($nodes) == 0) return true;

        if (count($nodes) == 1) return $isInline($nodes[0]);

        $ret = true;
        foreach ($nodes as $n) {
            if (!$isInline($n)) {
                $ret = false;
                break;
            }
        }

        if ($ret) {
            $prev = null;
            foreach ($nodes as $k => $n) {
                if ($prev !== null && $nodes[$prev]->isText && $n->isText) {
                    return false;
                }
                $prev = $k;
            }
            return true;
        }

        return false;
    }
}
