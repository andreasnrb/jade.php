<?php

namespace Jade\Nodes;

class Node {
    public $isBlock = false;
    public $isText = false;
    public $nodes = array();
    public $line;
    public $filename;
    public $debug;
}
