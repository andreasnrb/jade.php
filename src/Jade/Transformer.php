<?php

namespace Jade;


class Transformer {
    public $name;
    public $engines;
    public $isBinary;
    public $isMinifier;
    public $outputFormat;
    public $_cache;
    public $_renderAsync;
    public $sudoSync;
    public $_renderSync;
    public $sync;
    public $minifiers;
    public $cache;
    public $engine;
    public $minify;

    function Transformer($obj) {
        $obj = (object)$obj;
        $this->name = $obj->name;
        $this->engines = $obj->engines;
        $this->isBinary = $obj->isBinary || false;
        $this->isMinifier = $obj->isMinifier || false;
        $this->outputFormat = $obj->outputFormat;
        $this->_cache = [];
        if (is_callable($obj->async)) {
            $this->_renderAsync = $obj->async;
            $this->sudoSync = $obj->sudoSync || false;
        }
        if (is_callable($obj->sync)) {
            $this->_renderSync = $obj->sync;
            $this->sync = true;
        } else {
            $this->sync = $obj->sudoSync || false;
        }

        if ($this->isMinifier)
            $this->minifiers[$this->outputFormat] = $this;
        else {
            /**
             * @var Transformer $minifier
             */
            $minifier = $this->minifiers[$this->outputFormat];
            if ($minifier) {
                $this->minify = function ($str, $options) use (&$minifier) {
                    if ($options && $$options->minify)
                        return $minifier->renderSync($str, is_object($options->minify) && $options->minify || new \stdClass());
                    return $str;
                };
            }
        }
    }

    public function cache($options, $data) {
        if ($options->cache && $options->filename) {
            if ($data) return $this->cache[$options->filename] = $data;
            else return $this->cache[$options->filename];
        } else {
            return $data;
        }
    }

    public function loadModule() {
        if ($this->engine) return $this->engine;
        for ($i = 0; $i < sizeof($this->engines); $i++) {
            try {
                $res = $this->engines[$i] === '.' ? null : ($this->engine = require($this->engines[$i]));
                $this->engineName = $this->engines[$i];
                return $res;
            } catch (\Exception $ex) {
                if (sizeof($this->engines) === 1) {
                    throw $ex;
                }
            }
        }
        throw new \Exception('In order to apply the transform ' . $this->name . ' you must install one of ' . join('', array_map(function ($e) {
                return '"' . $e . '"';
            }, $this->engines)));
    }

    public function minify($str, $options) {
        return $str;
    }

    /**
     * @param $str
     * @param $options
     * @return mixed
     * @throws \Exception
     */
    public function renderSync($str, $options) {
        $options = $options || [];
        $options = $this->_clone($options);
        $this->loadModule();
        if ($this->_renderSync) {
            $renderAsync = $this->_renderAsync;
            $minify = $this->minify;
            return $minify($renderAsync(($this->isBinary ? $str : $this->fixString($str)), $options), $options);
        } else if ($this->sudoSync) {
            $options->sudoSync = true;
            $res = $err = '';
            $renderAsync = $this->_renderAsync;
            $renderAsync(($this->isBinary ? $str : $this->fixString($str)), $options, function ($e, $val) use(&$err, &$res) {
                if ($e) $err = $e;
                else $res = $val;
            });
            if ($err) throw new \Exception($err);
            else if ($res) {$minify = $this->minify; return $minify($res, $options);}
            else if (is_string($this->sudoSync)) throw new \Exception(preg_replace('/FILENAME/', $options['filename'] || '', $this->sudoSync));
            else throw new \Exception('There was a problem transforming ' . ($options['filename'] || '') . ' syncronously using ' . $this->name);
        } else {
            throw new \Exception($this->name . ' does not support transforming syncronously.');
        }
    }

    /**
     * @param $str
     * @param array $options
     * @return mixed
     */
    public function render($str, $options = []) {
        return $this->renderSync($str, $options);
    }

    /**
     * @param $path
     * @param $options
     * @return $this
     */
    public function renderFile($path, $options = []) {
            $options->filename = ($path = $this->normalize($path));
            if ($this->_cache[$path])
                return $this->_cache[$path];
            else {
                $str = $this->readFile($path);
                return $this->render($str, $options);
            }
    }

    /**
     * @param $path
     * @param $options
     * @return mixed
     */
    public function renderFileSync($path, $options) {
        $options = $options || [];
        $options['filename'] = ($path = $this->normalize($path));
        return $this->renderSync(($this->_cache[$path] ? null : $this->readFileSync($path)), $options);
    }

    function fixString($str) {
        if ($str == null) return $str;
        //convert buffer to string
        $str = $str . '';
        // Strip UTF-8 BOM if it exists
        $str = (0xFEFF == ord($str, 0)
            ? mb_substr($str, 1)
            : $str);
        //remove `\r` added by windows
        return preg_replace('/\r/', '', $str);
    }

    /**
     * @param array|object $obj
     * @return array
     */
    public function _clone($obj) {
        if (is_array($obj)) {
            return array_map($obj, array($this, '_clone'));
        } else if ($obj && is_object($obj)) {
            $res = [];
            $obj = get_object_vars($obj);
            foreach ($obj as $key => $val) {
                $res[$key] = clone($val);
            }
            return $res;
        } else {
            return $obj;
        }
    }

    private function normalize($path) {
        return realpath($path);
    }

    /**
     * @param $path
     * @return string
     */
    private function readFile($path) {
        return $this->readFileSync($path);
    }

    /**
     * @param $path
     * @return string
     */
    private function readFileSync($path) {
        return file_get_contents($path);
    }
}