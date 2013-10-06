<?php
/**
 * Created by PhpStorm.
 * User: Andreas
 * Date: 2013-10-06
 * Time: 01:36
 */

namespace Jade;


class CharacterParser {

    public static function parseMax($input, $start) {
        $paran = $brackets = $curly = 0;
        $string = mb_substr($input, $start);
        $size = mb_strlen($string);
        for($charPos =0; $charPos < $size; $charPos++) {
            switch($string[$charPos]) {
                case '(':
                    $paran++;
                    break;
                case ')':
                    $paran--;
                    break;
                case '{':
                    $curly++;
                    break;
                case '}':
                    $curly--;
                    break;

                case '[':
                    $brackets++;
                    break;
                case ']':
                    $brackets--;
                    break;
            }
            if ($paran<0 || $curly<0 || $brackets<0) {
                $obj = new \stdClass();
                $obj->start = $start;
                $obj->end = $charPos;
                $obj->src = mb_substr($string, 0, $charPos);
                return $obj;
            }
        }
        return null;
    }
} 