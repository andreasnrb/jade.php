<?php
namespace Jade;


class CharacterParser {
    public function parse($src, $state, $start = 0, $end = 0) {
        $state = $state ? $state : $this->defaultState();
        $end = $end || mb_strlen($src);
        $index = $start;
        while ($index < $end) {
            if ($state->roundDepth < 0 || $state->curlyDepth < 0 || $state->squareDepth < 0) {
                throw new \Exception('Mismatched Bracket: ' . $src[$index - 1]);
            }
            $this->parseChar($src[$index++], $state);
        }
        return $state;
    }

    public static function parseMax($input, $start = 0) {
        $paran = $brackets = $curly = 0;
        $string = mb_substr($input, $start);
        $size = mb_strlen($string);
        for ($charPos = 0; $charPos < $size; $charPos++) {
            switch ($string[$charPos]) {
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
            if ($paran < 0 || $curly < 0 || $brackets < 0) {
                $obj = new \stdClass();
                $obj->start = $start;
                $obj->end = $charPos+1;
                $obj->src = mb_substr($string, 0, $charPos);
                return $obj;
            }
        }
        return null;
    }

    /**
     * @param $character
     * @param State|null $state
     * @return State|null
     * @throws \Exception
     */
    public function parseChar($character, $state = null) {
        if (mb_strlen($character) !== 1) throw new \Exception('Character must be a string of length 1');
        $state = $state ? $state : $this->defaultState();
        $wasComment = $state->blockComment || $state->lineComment;
        $lastChar = $state->history ? $state->history[0] : '';
        if ($state->lineComment) {
            if ($character === '\n') {
                $state->lineComment = false;
            }
        } else if ($state->blockComment) {
            if ($lastChar === '*' && $character === '/') {
                $state->blockComment = false;
            }
        } else if ($state->singleQuote) {
            if ($character === '\'' && !$state->escaped) {
                $state->singleQuote = false;
            } else if ($character === '\\' && !$state->escaped) {
                $state->escaped = true;
            } else {
                $state->escaped = false;
            }
        } else if ($state->doubleQuote) {
            if ($character === '"' && !$state->escaped) {
                $state->doubleQuote = false;
            } else if ($character === '\\' && !$state->escaped) {
                $state->escaped = true;
            } else {
                $state->escaped = false;
            }
        } else if ($state->regexp) {
            if ($character === '/' && !$state->escaped) {
                $state->regexp = false;
            } else if ($character === '\\' && !$state->escaped) {
                $state->escaped = true;
            } else {
                $state->escaped = false;
            }
        } else if ($lastChar === '/' && $character === '/') {
            $state->history = mb_substr($state->history, 1);
            $state->lineComment = true;
        } else if ($lastChar === '/' && $character === '*') {
            $state->history = mb_substr($state->history, 1);
            $state->blockComment = true;
        } else if ($character === '/' && $this->isRegexp($state->history)) {
            $state->regexp = true;
        } else if ($character === '\'') {
            $state->singleQuote = true;
        } else if ($character === '"') {
            $state->doubleQuote = true;
        } else if ($character === '(') {
            $state->roundDepth++;
        } else if ($character === ')') {
            $state->roundDepth--;
        } else if ($character === '{') {
            $state->curlyDepth++;
        } else if ($character === '}') {
            $state->curlyDepth--;
        } else if ($character === '[') {
            $state->squareDepth++;
        } else if ($character === ']') {
            $state->squareDepth--;
        }
        if (!$state->blockComment && !$state->lineComment && !$wasComment) $state->history = $character . $state->history;
        return $state;
    }

    /**
     * @return State
     */
    public function defaultState() {
        return new State();
    }


    public static function isPunctuator($c) {
        $code = ord($c[0]);

        switch ($code) {
            case 46: // . dot
            case 40: // ( open bracket
            case 41: // ) close bracket
            case 59: // ; semicolon
            case 44: // , comma
            case 123: // { open curly brace
            case 125: // } close curly brace
            case 91: // [
            case 93: // ]
            case 58: // :
            case 63: // ?
            case 126: // ~
            case 37: // %
            case 38: // &
            case 42: // *:
            case 43: // +
            case 45: // -
            case 47: // /
            case 60: // <
            case 62: // >
            case 94: // ^
            case 124: // |
            case 33: // !
            case 61: // =
                return true;
            default:
                return false;
        }
    }

    function isRegexp($history) {
        //could be start of regexp or divide sign

        $history = preg_replace('/^\s*/', '', $history);

        //unless its an `if`, `while`, `for` or `with` it's a div$ide, so we assume it's a div$ide
        if ($history[0] === ')') return false;
        //unless it's a function expression, it's a regexp, so we assume it's a regexp
        if ($history[0] === '}') return true;
        //any punctuation means it's a regexp
        if (CharacterParser::isPunctuator($history[0])) return true;
        //if the last thing was a keyword then it must be a regexp (e.g. `typeof /foo/`)
        if (preg_match('/^\w+\b/', $history)) {
            preg_match('/^\w+\b/', $history, $match);
            $temp = explode('', $match[0]);
            $temp = array_reverse($temp);
            $testKeyword = join('', $temp);
            if ($this->isKeyword($testKeyword))
                return true;
        }
        return false;
    }


    function isKeyword($id) {
        return ($id === 'if') || ($id === 'in') || ($id === 'do') || ($id === 'var') || ($id === 'for') || ($id === 'new') ||
        ($id === 'try') || ($id === 'let') || ($id === 'this') || ($id === 'else') || ($id === 'case') ||
        ($id === 'vo$id') || ($id === 'with') || ($id === 'enum') || ($id === 'while') || ($id === 'break') || ($id === 'catch') ||
        ($id === 'throw') || ($id === 'const') || ($id === 'yield') || ($id === 'class') || ($id === 'super') ||
        ($id === 'return') || ($id === 'typeof') || ($id === 'delete') || ($id === 'switch') || ($id === 'export') ||
        ($id === 'import') || ($id === 'default') || ($id === 'finally') || ($id === 'extends') || ($id === 'function') ||
        ($id === 'continue') || ($id === 'debugger') || ($id === 'package') || ($id === 'private') || ($id === 'interface') ||
        ($id === 'instanceof') || ($id === 'implements') || ($id === 'protected') || ($id === 'public') || ($id === 'static') ||
        ($id === 'yield') || ($id === 'let');
    }
}

class State {
    public $lineComment = false;
    public $blockComment = false;

    public $singleQuote = false;
    public $doubleQuote = false;
    public $regexp = false;
    public $escaped = false;

    public $roundDepth = 0;
    public $curlyDepth = 0;
    public $squareDepth = 0;

    public $history = '';

    public function isString() {
        return $this->singleQuote || $this->doubleQuote;
    }

    public function isComment() {
        return $this->lineComment || $this->blockComment;
    }

    public function isNesting() {
        return $this->isString() || $this->isComment() || $this->regexp || $this->roundDepth > 0 || $this->curlyDepth > 0 || $this->squareDepth > 0;
    }

    public function startsWith($str, $start, $i) {
        return mb_substr($str, $i ? $i : 0, mb_strlen($start)) === $start;
    }
}
