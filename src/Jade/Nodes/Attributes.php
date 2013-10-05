<?php

namespace Jade\Nodes;

class Attributes extends Node {
    public $attributes = array();

    /**
     * @param string $name
     * @param string $value
     * @param bool $escaped
     * @return $this
     */
    public function setAttribute($name, $value, $escaped=false) {
        array_push($this->attributes, array('name'=>$name,'value'=>$value,'escaped'=>$escaped));
        return $this;
    }

    /**
     * @param string $name
     */
    public function removeAttribute($name) {
        foreach ($this->attributes as $k => $attr) {
            if ($attr['name'] == $name) {
                unset($this->attributes[$k]);
            }
        }
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getAttribute($name) {
        foreach ($this->attributes as $attr) {
            if ($attr['name'] == $name) {
                return $attr;
            }
        }
        return null;
    }
}
