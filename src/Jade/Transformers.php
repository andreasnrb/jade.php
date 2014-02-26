<?php
/**
 * Created by PhpStorm.
 * 'User' => Andreas
 * 'Date' => 2013-10-10
 * 'Time' => '23' =>10
 */

namespace Jade;


class Transformers {
    private $transformers = array();
    function _construct() {
        $this->transformers['css'] = new Transformer([
"name" => 'css',
"engines" => ['.'],// `.` means "no dependency"
"outputFormat" => 'css',
"sync" => function ($str, $options) {
    return $this->cache($options) || $this->cache($options, $str);}
]
    );

    $this->transformers['js'] = new Transformer([
    "name" => 'js',
  "engines" => ['.'],// `.` means "no dependency"
  "outputFormat" => 'js',
        "sync" => function ($str, $options) {
                return $this->cache($options) || $this->cache($options, $str);}
]);
        $this->transformers['cdata']= new Transformer([
            'name' => 'cdata',
  'engines' => ['.'],// `.` means "no dependency"
  'outputFormat' => 'xml',
  'sync' => function ($str, $options) {
                $escaped = preg_replace('/\]\]>/', "]]]]><![CDATA[>", $str);
    return $this->cache($options) || $this->cache($options, '<![CDATA[' . $escaped . ']]>');
  }
]);

$this->transformers['cdata-js']= new Transformer([
            'name' => 'cdata-js',
  'engines' => ['.'],// `.` means "no dependency"
  'outputFormat' => 'xml',
  'sync' => function ($str,  $options) {
                $escaped = preg_replace('/\]\]>/', "]]]]><![CDATA[>", $str);
    return $this->cache($options) || $this->cache($options, '//<![CDATA[\n' . $escaped . '\n//]]>');
  }]);

$this->transformers['cdata-css']= new Transformer([
            'name' => 'cdata-css',
  'engines' => ['.'],// `.` means "no dependency"
  'outputFormat' => 'xml',
  'sync' => function ($str,  $options) {
                $escaped = preg_replace('/\]\]>/', "]]]]><![CDATA[>",$str);
    return $this->cache($options) || $this->cache($options, '/*<![CDATA[*/\n' . $escaped . '\n/*]]>*/');
  }
]);

$this->transformers['verbatim']= new Transformer([
            'name' => 'verbatim',
  'engines' => ['.'],// `.` means "no dependency"
  'outputFormat' => 'xml',
  'sync' => function ($str,  $options) {
                return $this->cache($options) || $this->cache($options, $str);
            }
]);

        $this->transformers['jade']= new Transformer([
            'name' => 'jade',
  'engines' => ['jade'],
  'outputFormat' => 'xml',
  'sudoSync' => 'The jade file FILENAME could not be rendered syncronously.  N.B. then-jade does not support syncronous rendering.',
  'async' => function ($str,  $options) {
                $this->cache($options, true);//jade handles $this->cache internally
                $this->engine->render($str,  $options);
            }
]);
    }

    /**
     * @param $options
     * @param string $str
     * @return bool
     */
    private function cache($options, $str = '') {
        return true;
    }
}