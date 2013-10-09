<?php

namespace Jade;

class Lexer {

    public $lineno = 1;
    public $pipeless;
    public $input;

    protected $deferred		= array();

    protected $indentStack	= array();
    protected $stash		= array();
    private $colons;

    public function __construct($input) {
        $this->setInput($input);
    }

    /**
     * Set lexer input.
     *
     * @param   string  $input  input string
     */
    public function setInput($input) {
        $this->input		= preg_replace("/\r\n|\r/", "\n", $input);
        $this->lineno       = 1;
        $this->deferred		= array();
        $this->indentStack  = array();
        $this->stash        = array();
    }

    /**
     * Construct token with specified parameters.
     *
     * @param   string  $type   token type
     * @param   string  $value  token value
     *
     * @return  Object          new token object
     */
    public function token($type, $value = null) {
        return (object) array(
            'type'  => $type
            , 'line'  => $this->lineno
            , 'value' => $value
        );
    }

    function length() {
        return mb_strlen($this->input);
    }

    /**
     * Consume input.
     *
     * @param   string $bytes utf8 string of input to consume
     */
    protected function consume($bytes) {
        $len = is_int($bytes) ? $bytes : mb_strlen($bytes);
        $this->input = mb_substr($this->input, $len);
    }

    protected function normalizeCode($code) {
        // everzet's implementation used ':' at the end of the code line as in php's alternative syntax
        // this implementation tries to be compatible with both, js-jade and jade.php, so, remove the colon here
        return $code = (substr($code,-1) == ':') ? substr($code,0,-1) : $code;
    }

    /**
     *  Helper to create tokens
     */
    protected function scan($regex, $type) {

        if( preg_match($regex, $this->input, $matches) ){
            $this->consume($matches[0]);
            return $this->token($type, isset($matches[1]) && mb_strlen($matches[1]) > 0 ? $matches[1] : '' );
        }
    }

    /**
     * Defer token.
     *
     * @param \stdClass $token token to defer
     */
    public function defer(\stdClass $token) {
        $this->deferred[] = $token;
    }

    /**
     * Lookahead token 'n'.
     *
     * @param   integer     $number number of tokens to predict
     *
     * @return  Object              predicted token
     */
    public function lookahead($number = 1) {
        $fetch = $number - count($this->stash);

        while ( $fetch-- > 0 )
            $this->stash[] = $this->next();

        return $this->stash[--$number];
    }

    /**
     * Return the indexOf `(` or `{` or `[` / `)` or `}` or `]` delimiters.
     *
     * @param int $skip
     * @return null|\stdClass
     * @throws \Exception
     */
    public function bracketExpression($skip=0) {
        $start = $this->input[$skip];
        if ($start != '(' && $start != '{' && $start != '[') throw new \Exception('unrecognized start character');
        $end = array('(' => ')', '{' =>  '}', '['  =>  ']');
        $range = (new CharacterParser())->parseMax($this->input, $skip + 1);
        if (is_null($range))  throw new \Exception('source does not have an end character bar starts with ' . $start);
        if ($this->input[$range->end] != $end[$start]) throw new \Exception('start character ' . $start .
            ' does not match end character ' . $this->input[$range->end]);

        return $range;
    }

    /**
     * Return stashed token.
     *
     * @return  Object|boolean   token if has stashed, false otherways
     */
    protected function getStashed() {
        return count($this->stash) ? array_shift($this->stash) : null;
    }

    /**
     * Return deferred token.
     *
     * @return  Object|boolean   token if has deferred, false otherways
     */
    protected function deferred() {
        return count($this->deferred) ? array_shift($this->deferred) : null;
    }

    /**
     * Return next token or previously stashed one.
     *
     * @return  Object
     */
    public function advance() {
        $token = $this->getStashed()
            or $token = $this->next();

        return $token;
    }

    /**
     * Return next token.
     *
     * @return  Object
     */
    protected function next() {
        return $this->nextToken();
    }

    /**
     * Scan EOS from input & return it if found.
     *
     * @return  Object|null
     */
    protected function scanEOS() {
        if ($this->length()) return null;
        if (count($this->indentStack)) {
            array_shift($this->indentStack);
            return $this->token('outdent');
        }
        return $this->token('eos');
    }

    protected function scanBlank() {
        if( preg_match('/^\n *\n/', $this->input, $matches) ){
            $this->consume(mb_substr($matches[0],0,-1)); // do not cosume the last \r
            $this->lineno++;
            if ($this->pipeless) return $this->token('text','');
            return $this->next();
        }
    }

    /**
     * Scan comment from input & return it if found.
     *
     * @return  Object|null
     */
    protected function scanComment() {

        if ( preg_match('/^ *\/\/(-)?([^\n]*)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $token = $this->token('comment', isset($matches[2]) ? $matches[2] : '');
            $token->buffer = '-' !== $matches[1];

            return $token;
        }
    }

    protected function scanInterpolation() {
        if (preg_match('/^\#{(.*?)}/', $this->input)) {
            $match = $this->bracketExpression(1);
            $this->consume($match->end + 1);
            return $this->token('interpolation', $match->src);
        }
    }

    protected function scanTag() {

        if ( preg_match('/^(\w[-:\w]*)(\/?)/',$this->input,$matches) ) {
            $this->consume($matches[0]);
            $name = $matches[1];

            if ( ':' == mb_substr($name,-1) ) {

                $name = mb_substr($name,0,-1);
                $token = $this->token('tag',$name);
                $this->defer($this->token(':'));

                while ( ' ' == mb_substr($this->input,0,1) ) $this->consume(mb_substr($this->input,0,1));
            } else {
                $token = $this->token('tag', $name);
            }

            $token->selfClosing = $matches[2] == '/';

            return $token;
        }
    }

    protected function scanFilter() {
        return $this->scan('/^:(\w+)/', 'filter');
    }

    protected function scanDoctype() {
        return $this->scan('/^(?:!!!|doctype) *([^\n]+)?/', 'doctype');
    }

    protected function scanId() {
        return $this->scan('/^#([\w-]+)/','id');
    }

    protected function scanClassName() {
        // http://www.w3.org/TR/CSS21/grammar.html#scanner
        //
        // ident:
        //		-?{nmstart}{nmchar}*
        // nmstart:
        //		[_a-z]|{nonascii}|{escape}
        // nonascii:
        //		[\240-\377]
        // escape:
        //		{unicode}|\\[^\r\n\f0-9a-f]
        // unicode:
        //		\\{h}{1,6}(\r\n|[ \t\r\n\f])?
        // nmchar:
        //		[_a-z0-9-]|{nonascii}|{escape}
        //
        // /^(-?(?!=[0-9-])(?:[_a-z0-9-]|[\240-\377]|\\{h}{1,6}(?:\r\n|[ \t\r\n\f])?|\\[^\r\n\f0-9a-f])+)/
        return $this->scan('/^[.]([\w-]+)/','class');
    }

    protected function scanText() {
        if (preg_match('/^([^\.\<][^\n]+)/',$this->input)
            && !preg_match('/^(?:\| ?| )([^\n]+)/', $this->input)) {
            throw new \Exception('Warning: missing space before text for line ' . $this->lineno . ' of jade file.');
        }
        $scanned =  $this->scan('/^(?:\| ?| )([^\n]+)/', 'text') or $scanned = $this->scan('/^([^\.][^\n]+)/', 'text');
        return $scanned;
    }

    protected function scanDot() {
        return $this->scan('/^\./', 'dot');
    }

    protected function scanExtends() {
        return $this->scan('/^extends? +([^\n]+)/','extends');
    }

    protected function scanPrepend() {
        if ( preg_match('/^prepend +([^\n]+)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $token = $this->token('block', $matches[1]);
            $token->mode = 'prepend';
            return $token;
        }
    }

    protected function scanAppend() {

        if( preg_match('/^append +([^\n]+)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $token = $this->token('block', $matches[1]);
            $token->mode = 'append';
            return $token;
        }
    }

    protected function scanBlock() {
        if( preg_match("/^block\b *(?:(prepend|append) +)?([^\n]+)/", $this->input, $matches) ) {
            $this->consume($matches[0]);
            $token = $this->token('block', $matches[2]);
            $token->mode = (mb_strlen($matches[1]) == 0) ? 'replace' : $matches[1];
            return $token;
        }
    }

    /**
     * Mixin Block.
     */
    protected function scanMixinBlock() {
        if (preg_match('/^block\s*\n/', $this->input, $matches)) {
            $this->consume(mb_strlen($matches[0]) - 1);
            return $this->token('mixin-block');
        }
    }

    protected function scanYield() {
        return $this->scan('/^yield */', 'yield');
    }

    protected function scanInclude() {
        return $this->scan('/^include +([^\n]+)/', 'include');
    }

    protected function scanCase() {
        return $this->scan('/^case +([^\n]+)/', 'case');
    }

    protected function scanWhen() {
        return $this->scan('/^when +([^:\n]+)/', 'when');
    }

    protected function scanDefault() {
        return $this->scan('/^default */', 'default');
    }

    protected function scanAssignment() {
        if ( preg_match('/^(\w+) += *(\'[^\']+\'|"[^"]+"|[^;\n]+)( *;? *)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $name = $matches[1];
            $val = trim($matches[2]);
            if ($val[($len = mb_strlen($val)) - 1] === ';') {
                $val = mb_substr($val, 0, $len - 1);
            }
            $this->assertExpression($val);
            return $this->token('code', $name . ' = ' . $val);
        }
    }

    protected function scanCall() {
        if ( preg_match('/^\+([-\w]+)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $token = $this->token('call', $matches[1]);

            # check for arguments
            if ( preg_match( '/^ *\((.*?)\)/', $this->input, $matches_arguments) ) {
                $range = $this->bracketExpression(mb_strlen($matches_arguments[0]));
                if (0 == preg_match('-/^ *[-\w]+ *=/', $range->src)) {
                    $this->consume($range->end + 1);
                    $token->arguments = $range->src;
                }
            }
            return $token;
        }
    }

    protected function scanMixin() {
        if ( preg_match('/^mixin +([-\w]+)(?: *\((.*)\))? */', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $token = $this->token('mixin', $matches[1]);
            $token->arguments = isset($matches[2]) ? $matches[2] : null;
            return $token;
        }
    }

    protected function scanConditional() {
        if ( preg_match('/^(if|unless|else if|else)\b([^\n]*)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $type = $matches[1];
            $code = $matches[2];

            switch ($type) {
                case 'if':
                    $this->assertExpression($code);
                    $code = 'if (' . $code . '):';
                    break;
                case 'unless':
                    $this->assertExpression($code);
                    $code = 'if (!(' . $code . ')):';
                    break;
                case 'else if':
                    $this->assertExpression($code);
                    $code = 'elseif (' . $code . '):';
                    break;
                case 'else':
                    if ($code && trim($code)) {
                        throw new \Exception('`else` cannot have a condition, perhaps you meant `else if`');
                    }
                    $this->assertExpression($code);
                    $code = 'else:';
                    break;
            }

            $code   = $this->normalizeCode($code);
            $token  = $this->token('code', $code);
            $token->buffer = false;
            return $token;
        }
    }


    protected function scanWhile() {
        if ( preg_match('/^while +([^\n]+)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $this->assertExpression($matches[1]);
            $this->token('code', 'while (' . $matches[1] . '):');
        }
    }

    protected function scanEach() {
        if ( preg_match('/^(?:- *)?(?:each|for) +([a-zA-Z_$][\w$]*)(?: *, *([a-zA-Z_$][\w$]*))? * in *([^\n]+)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $token = $this->token('each', $matches[1]);
            $token->key = $matches[2];
            $this->assertExpression($matches[3]);
            $token->code = $this->normalizeCode($matches[3]);

            return $token;
        }
    }

    protected function scanCode() {
        if ( preg_match('/^(!?=|-)[ \t]*([^\n]+)/', $this->input, $matches) ) {
            $this->consume($matches[0]);
            $flags  = $matches[1];
            $matches[1] = $matches[2];
            $code   = $this->normalizeCode($matches[1]);

            $token = $this->token('code', $code);
            $token->escape = $flags[0] === '=';
            $token->buffer = '=' === $flags[0] || (isset($flags[1]) && '=' === $flags[1]);
            if ($token->buffer) $this->assertExpression($matches[1]);
            return $token;
        }
    }

    protected function scanAttributes() {
        if ($this->input[0] === '(') {
            $index = $this->bracketExpression()->end;
            $str = mb_substr($this->input, 1, $index-1);
            $equals = '=';
            $this->assertNestingCorrect($str);
            $this->consume($index + 1);
            $token = $this->token('attributes');
            $token->attributes = array();
            $token->escaped = array();
            $token->selfClosing = false;

            $key = '';
            $val = '';
            $quote = '';
            $escapedAttr = true;
            $interpolatable = '';
            $loc = 'key';
            $characterParser = new CharacterParser();
            $state = $characterParser->defaultState();

            $isEndOfAttribute = function ($i) use (&$key, &$str, &$loc, &$state, &$val) {
                if (trim($key) === '') return false;
                if (($i) === mb_strlen($str)) {
                    return true;
                }
                if ('key' === $loc) {
                    if ($str[$i] === ' ' || $str[$i] === "\n" || $str[$i] === "\r\n") {
                        for ($x = $i; $x < mb_strlen($str); $x++) {
                            if ($str[$x] !== ' ' && $str[$x] !== "\n" && $str[$x] !== "\r\n") {
                                if ($str[$x] === '=' || $str[$x] === '!' || $str[$x] === ',') return false;
                                else return true;
                            }
                        }
                    }
                    return $str[$i] === ',';
                } else if ($loc === 'value' && !$state->isNesting()) {
                    try {
                        $this->assertExpression($val);
                        //TODO: Something wrong here so cant support spaces as attribute separators
                        /*if ($str[$i] === ' ' || $str[$i] === "\n" || $str[$i] === "\r\n") {
                            for ($x = $i; $x < mb_strlen($str); $x++) {
                                if ($str[$x] != ' ' && $str[$x] != "\n" && $str[$x] != "\r\n") {
                                    if (CharacterParser::isPunctuator($str[$x]) && $str[$x] != '"' && $str[$x] != "'")
                                        return false;
                                    else
                                        return true;
                                }
                            }
                        }*/
                        return $str[$i] === ',';
                    } catch (\Exception $ex) {
                        return false;
                    }
                }
                return false;
            };

            for ($i = 0; $i <= mb_strlen($str); $i++) {
                if ($isEndOfAttribute($i)) {
                    $val = trim($val);
                    if ($val) $this->assertExpression($val);
                    $key = trim($key);
                    $key = preg_replace('/^[\'"]|[\'"]$/', '', $key);
                    $token->escaped[$key] = $escapedAttr;
                    if (strpos($val, ' , \'') !== false)
                        $val = substr($val, 0, strpos($val, ' , \''));
                    $token->attributes[$key] = '' == $val ? true : $val;
                    $key = $val = '';
                    $loc = 'key';
                    $escapedAttr = false;
                } else {
                    switch ($loc) {
                        case 'key-char':
                            if ($str[$i] === $quote) {
                                $loc = 'key';
                                if ($i + 1 < mb_strlen($str) && !in_array($str[$i + 1], [' ', ',', '!', $equals, "\n", "\r\n"]))
                                    throw new \Exception('Unexpected character ' . $str[$i + 1] . ' expected ` `, `\\n`, `,`, `!` or `=`');
                            } else if ($loc === 'key-char') {
                                $key .= $str[$i];
                            }
                            break;
                        case 'key':
                            if (empty($key) && ($str[$i] === '"' || $str[$i] === "'")) {
                                $loc = 'key-char';
                                $quote = $str[$i];
                            } else if ($str[$i] === '!' || $str[$i] === $equals) {
                                $escapedAttr = $str[$i] !== '!';
                                if ($str[$i] === '!') $i++;
                                if ($str[$i] !== $equals) throw new \Exception('Unexpected character ' . $str[$i] . ' expected `=`');
                                $loc = 'value';
                                $state = $characterParser->defaultState();
                            } else {
                                $key .= $str[$i];
                            }

                            break;
                        case 'value':
                            $state = $characterParser->parseChar($str[$i], $state);
                            if ($state->isString()) {
                                $loc = 'string';
                                $quote = $str[$i];
                                $interpolatable = $str[$i];
                            } else {
                                $val .= $str[$i];
                            }
                            break;
                        case 'string':
                            $state = $characterParser->parseChar($str[$i], $state);
                            $interpolatable .= $str[$i];
                            if (!$state->isString()) {
                                $loc = 'value';
                                $val .= $this->interpolate($interpolatable, $quote);
                            }
                            break;
                    }
                }
            }
            if (isset($this->input[0]) && '/' == $this->input[0]) {
                $this->consume(1);
                $token->selfClosing = true;
            }
            return $token;
        }
    }

    private function interpolate ($attr, $quote) {
        return str_replace('\#{', '#{', preg_replace_callback ('/(\\\\)?#{(.+)/', function ($_) use (&$quote) {
            $escape = $_[1];
            $expr = $_[2];
            $_ = $_[0];
            if ($escape) return $_;
            try {
                $range = (new CharacterParser())->parseMax($expr);
                if ($expr[$range->end] !== '}') return mb_substr($_, 0, 2) . $this->interpolate(mb_substr($_, 2), $quote);
                self::assertExpression($range->src);
                return $quote . " , (" . $range->src . ") , " . $quote . $this->interpolate(mb_substr($expr, $range->end + 1), $quote);
            } catch (\Exception $ex) {
                return mb_substr($_, 0, 2) . $this->interpolate(mb_substr($_, 2), $quote);
            }
        }, $attr));
    }

    protected function scanIndent() {

        if (isset($this->identRE)) {
            $ok = preg_match($this->identRE, $this->input, $matches);
        }else{
            $re = "/^\n(\t*) */";
            $ok = preg_match($re, $this->input, $matches);

            if ($ok && mb_strlen($matches[1]) == 0) {
                $re = "/^\n( *)/";
                $ok = preg_match($re, $this->input, $matches);
            }

            if ($ok && mb_strlen($matches[1]) != 0) {
                $this->identRE = $re;
            }
        }

        if ($ok) {
            $indents = mb_strlen($matches[1]);

            $this->lineno++;
            $this->consume($matches[0]);

            if ($this->length() && (' ' == $this->input[0] || "\t" == $this->input[0])) {
                throw new \Exception('Invalid indentation, you can use tabs or spaces but not both');
            }

            if ($this->length() && $this->input[0] === "\n") {
                return $this->token('newline');
            }

            if (count($this->indentStack) && $indents < $this->indentStack[0]) {
                while (count($this->indentStack) && $indents < $this->indentStack[0]) {
                    array_push($this->stash, $this->token('outdent'));
                    array_shift($this->indentStack);
                }
                return array_pop($this->stash);
            }

            if ($indents && count($this->indentStack) && $indents == $this->indentStack[0]) {
                return $this->token('newline');
            }

            if ($indents) {
                array_unshift($this->indentStack, $indents);
                return $this->token('indent', $indents);
            }

            return $this->token('newline');
        }
    }

    protected function scanPipelessText() {
        if ($this->pipeless && "\n" != $this->input[0]) {
            $i = mb_strpos($this->input, "\n");

            if ($i === false) {
                $i = $this->length();
            }

            $str = mb_substr($this->input,0,$i); // do not include the \n char
            $this->consume($str);
            return $this->token('text', $str);
        }
    }

    protected function scanColon() {
        return $this->scan('/^: */', ':');
    }

    public function nextToken() {
        $r = $this->deferred()
            or $r = $this->scanBlank()
            or $r = $this->scanEOS()
            or $r = $this->scanPipelessText()
            or $r = $this->scanYield()
            or $r = $this->scanDoctype()
            or $r = $this->scanInterpolation()
            or $r = $this->scanCase()
            or $r = $this->scanWhen()
            or $r = $this->scanDefault()
            or $r = $this->scanExtends()
            or $r = $this->scanAppend()
            or $r = $this->scanPrepend()
            or $r = $this->scanBlock()
            or $r = $this->scanMixinBlock()
            or $r = $this->scanInclude()
            or $r = $this->scanMixin()
            or $r = $this->scanCall()
            or $r = $this->scanConditional()
            or $r = $this->scanEach()
            or $r = $this->scanWhile()
            or $r = $this->scanAssignment()
            or $r = $this->scanTag()
            or $r = $this->scanFilter()
            or $r = $this->scanCode()
            or $r = $this->scanId()
            or $r = $this->scanClassName()
            or $r = $this->scanAttributes()
            or $r = $this->scanIndent()
            or $r = $this->scanComment()
            or $r = $this->scanColon()
            or $r = $this->scanText()
            or $r = $this->scanDot();

        return $r;
    }

    /**
     * @deprecated Use lookahead instead
     */
    public function predictToken($number = 1) {
        $this->lookahead($number);
    }
    /**
     * @deprecated Use advance instead
     */
    public function getAdvancedToken() {
        return $this->advance();
    }

    private static function assertExpression($val) {
        if (@eval($val."\n")) {
            throw new \Exception(sprintf('Not correct expression, expressions need to return true: %s', $val));
        }
    }

    private function assertNestingCorrect($str) {
    }
}
