<?php

namespace Jade;

use Jade\Nodes\Block;
use Jade\Nodes\Code;
use Jade\Nodes\Comment;
use Jade\Nodes\Doctype;
use Jade\Nodes\Each;
use Jade\Nodes\MixinBlock;
use Jade\Nodes\Node;
use Jade\Nodes\Tag;
use Jade\Nodes\Text;

class Compiler2 {
    public $withinCase;
    public $runtime;
    private $selfClosing = [
        'meta'
        , 'img'
        , 'link'
        , 'input'
        , 'source'
        , 'area'
        , 'base'
        , 'col'
        , 'br'
        , 'hr'
    ];

    private $options;
    private $node;
    private $hasCompiledDoctype;
    private $hasCompiledTag;
    private $pp;
    private $debug;
    private $inMixin;
    private $indents;
    private $parentIndents;
    private $buf;
    private $lastBufferedIdx;
    private $doctypes = [
        '5' =>'<!DOCTYPE html>'
        , 'default' => '<!DOCTYPE html>'
        , 'xml' => '<?xml version="1.0" encoding="utf-8" ?>'
        , 'transitional' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'
        , 'strict' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'
        , 'frameset' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">'
        , '1.1' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">'
        , 'basic' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML Basic 1.1//EN" "http://www.w3.org/TR/xhtml-basic/xhtml-basic11.dtd">'
        , 'mobile' => '<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.2//EN" "http://www.openmobilealliance.org/tech/DTD/xhtml-mobile12.dtd">'
    ];
    private $doctype;
    private $xml;
    private $terse;
    /**
     * @var CharacterParser
     */
    private $characterParser;
    private $lastBufferedType;
    private $lastBuffered;
    private $bufferStartChar;
    private $escape;

    /**
     * Initialize `Compiler` with the given `node`.
     *
     * @param Node $node
     * @param array $options
     */
    public function _construct($node, $options = array()) {
        $this->options = $options;
        $this->node = $node;
        $this->hasCompiledDoctype = false;
        $this->hasCompiledTag = false;
        $this->pp = $options['prettyprint'] || false;
        $this->debug = false !== $options['compileDebug'];
        $this->inMixin = false;
        $this->indents = 0;
        $this->parentIndents = 0;
        if (isset($options['doctype']))
            $this->setDoctype($options['doctype']);
    }

    /**
     * Compile parse tree to PHP.
     *
     * @return string
     */
    public function compile(){
        $this->characterParser = new CharacterParser();
        $this->buf = [];
        if ($this->pp) array_push($this->buf, "jade.indent = [];");
        $this->lastBufferedIdx = -1;
        $this->visit($this->node);
        return join($this->buf,'\n');
    }

    /**
     * Sets the default doctype `name`. Sets terse mode to `true` when
     * html 5 is used, causing self-closing tags to end with ">" vs "/>",
     * and boolean attributes are not mirrored.
     * @param $name
     */
    public function setDoctype($name) {
        $name = $name || 'default';
        $this->doctype = $this->doctypes[strtolower($name)] || "<!DOCTYPE $name >";
        $this->terse = strtolower($this->doctype) == '<!doctype html>';
        $this->xml = 0 == strpos($this->doctype, '<?xml');
    }

    /**
     * Buffer the given `str` exactly as is or with interpolation
     *
     * @param string $str
     * @param bool $interpolate
     * @throws
     */
    public function buffer($str, $interpolate = false) {
        if ($interpolate) {
            preg_match('/(\\)?([#!]){((?:.|\n)*)$/', $str, $match, 0, PREG_OFFSET_CAPTURE);
            if ($match) {
                /** match.index */
                $this->buffer($str . substr(0, $match[1][1]), false);
                if ($match[1]) { // escape
                    $this->buffer($match[2] . '{', false);
                    $this->buffer($match[3], true);
                    return;
                } else {
                    try {
                        $rest = $match[3];
                        $range = $this->parseJSExpression($rest);
                        $code = ('!' == $match[2] ? '' : 'jade.escape') . "((jade.interp = " . $range->src . ") == null ? '' : jade.interp)";
                    } catch (\Exception $ex) {
                        //didn't $match, just as if escaped
                        $this->buffer($match[2] . '{', false);
                        $this->buffer($match[3], true);
                        return;
                    }
                    $this->bufferExpression($code);
                    $this->buffer(mb_substr($rest, 0, $range->end + 1), true);
                    return;
                }
            }
            return;
        }

        $str = json_encode($str);
        $str = mb_substr($str, 1, mb_strlen($str) - 2);

        if ($this->lastBufferedIdx == mb_strlen($this->buf)) {
            if ($this->lastBufferedType === 'code') $this->lastBuffered .= ' + "';
            $this->lastBufferedType = 'text';
            $this->lastBuffered .= $str;
            $this->buf[$this->lastBufferedIdx - 1] = 'buf.push(' . $this->bufferStartChar . $this->lastBuffered . '");';
        } else {
            array_push($this->buf, 'buf.push("' . $str . '");');
            $this->lastBufferedType = 'text';
            $this->bufferStartChar = '"';
            $this->lastBuffered = $str;
            $this->lastBufferedIdx = mb_strlen($this->buf);
        }
    }

    /**
     * Buffer the given `src` so it is evaluated at run time
     *
     * @param $src
     */
    public function bufferExpression($src) {
        $fn = ''; //Function('', 'return (' + src + ');');
        if ($this->isConstant($src)) {
            $this->buffer($fn(), false);
            return;
        }
        if ($this->lastBufferedIdx == mb_strlen($this->buf)) {
            if ($this->lastBufferedType === 'text') $this->lastBuffered .= '"';
            $this->lastBufferedType = 'code';
            $this->lastBuffered .= ' + (' . $src . ')';
            $this->buf[$this->lastBufferedIdx - 1] = 'buf.push(' . $this->bufferStartChar . $this->lastBuffered . ');';
        } else {
            array_push($this->buf, 'buf.push(' . $src . ');');
            $this->lastBufferedType = 'code';
            $this->bufferStartChar = '';
            $this->lastBuffered = '(' . $src . ')';
            $this->lastBufferedIdx = mb_strlen($this->buf);
        }
    }

    /**
     * Buffer an indent based on the current `indent`
     * property and an additional `offset`.
     *
     * @param int $offset
     * @param Bool $newline
     * @api public
     */

    public function prettyIndent($offset = 0, $newline = true){
        $newline = $newline ? "\n" : '';
        $this->buffer($newline . join(array_fill (0,$this->indents + $offset,''),'  '));
        if ($this->parentIndents)
            array_push($this->buf, "buf.push.apply(buf, jade.indent);");
    }

    /**
     * Visit `node`.
     *
     * @param Node $node
     */
    public function visit($node){
        $debug = $this->debug;

        if ($debug) {
            array_push($this->buf,'jade.debug.unshift({ lineno: ' . $node->line
                . ', filename: ' . ($node->filename
                    ? json_encode($node->filename)
                    : 'jade.debug[0].filename')
                . ' });');
        }

        // Massive hack to fix our context
        // stack for - else[ if] etc
        if (false === $node->debug && $this->debug) {
            array_pop($this->buf);
            array_pop($this->buf);
        }

        $this->visitNode($node);

        if ($debug) array_push($this->buf, 'jade.debug.shift();');
    }

    /**
     * Visit `node`.
     *
     * @param {Node} node
     * @api public
     */

    public function visitNode($node){
        $name = get_class($node); // || $node->constructor.toString().match('/function ([^(\s]+)()/')[1];
        return $this->{'visit' . $name}($node);
    }

    /**
     * Visit case `node`.
     *
     * @param Node node
     * @api public
     */

    public function visitCase($node){
        $_ = $this->withinCase;
        $this->withinCase = true;
        array_push($this->buf,'switch (' . $node->expr . '){');
        $this->visit($node->block);
        array_push($this->buf, '}');
        $this->withinCase = $_;
    }

    /**
     * Visit when `node`.
     *
     * @param Node node
     * @api public
     */

    public function visitWhen($node){
        if ('default' == $node->expr) {
            array_push($this->buf, 'default:');
        } else {
            array_push($this->buf, 'case ' . $node->expr . ':');
        }
        $this->visit($node->block);
        array_push($this->buf,'  break;');
    }

    /**
     * Visit literal `node`.
     *
     * @param  Node node
     */

    public function visitLiteral($node){
        $this->buffer($node->str);
    }

    /**
     * Visit all nodes in `block`.
     *
     * @param Block $block
     * @api public
     */

    public function visitBlock($block) {
        $len = mb_strlen($block->nodes);
        $escape = $this->escape;
        $pp = $this->pp;

        // Pretty print multi-line text
        if ($pp && $len > 1 && !$escape && $block->nodes[0]->isText() && $block->nodes[1]->isText())
            $this->prettyIndent(1, true);

        for ($i = 0; $i < $len; ++$i) {
            // Pretty print text
            if ($pp && $i > 0 && !$escape && $block->nodes[$i]->isText() && $block->nodes[$i - 1]->isText())
                $this->prettyIndent(1, false);

            $this->visit($block->nodes[$i]);
            // Multiple text nodes are separated by newlines
            if ($block->nodes[$i + 1] && $block->nodes[$i]->isText() && $block->nodes[$i + 1]->isText())
                $this->buffer("\n");
        }
    }

    /**
     * Visit a mixin's `block` keyword.
     *
     * @param MixinBlock $block
     * @throws \Exception
     */
    public function visitMixinBlock($block) {
        if (!$this->inMixin) {
            throw new \Exception('Anonymous blocks are not allowed unless they are part of a mixin.');
        }
        if ($this->pp) array_push($this->buf, "jade.indent.push('" . join(array_fill(0, $this->indents + 1, '', ''), '  ') . "');");
        array_push($this->buf, 'block && block();');
        if ($this->pp) array_push($this->buf, "jade.indent.pop();");
    }

    /**
     * Visit `doctype`. Sets terse mode to `true` when html 5
     * is used, causing self-closing tags to end with ">" vs "/>",
     * and boolean attributes are not mirrored.
     *
     * @param Doctype $doctype
     * @api public
     */

    public function visitDoctype($doctype = null) {
        if ($doctype && ($doctype->value || !$this->doctype)) {
            $this->setDoctype($doctype->value || 'default');
        }

        if ($this->doctype) $this->buffer($this->doctype);
        $this->hasCompiledDoctype = true;
    }

    /**
     * Visit `mixin`, generating a function that
     * may be called within the template.
     *
     * @param {Mixin} mixin
     * @api public
     */

    public function visitMixin($mixin) {
        $name = preg_replace('/-/', '_', $mixin->name) . '_mixin';
        $args = $mixin->arguments || '';
        $block = $mixin->block;
        $attrs = $mixin->attributes;
        $pp = $this->pp;

        if ($mixin->call) {
            if ($pp) array_push($this->buf, "jade.indent.push('" . join(array_fill(0, $this->indents + 1, '', ''), '  ') . "');");
            if ($block || mb_strlen($attrs)) {

                array_push($this->buf, $name . '.call({');

                if ($block) {
                    array_push($this->buf, 'block: function(){');

                    // Render block with no indents, dynamically added when rendered
                    $this->parentIndents++;
                    $_indents = $this->indents;
                    $this->indents = 0;
                    $this->visit($mixin->block);
                    $this->indents = $_indents;
                    $this->parentIndents--;

                    if (mb_strlen($attrs)) {
                        array_push($this->buf, '},');
                    } else {
                        array_push($this->buf, '}');
                    }
                }

                if (mb_strlen($attrs)) {
                    $val = $this->attrs($attrs);
                    if ($val->inherits) {
                        array_push($this->buf, 'attributes: jade.merge({' . $val->buf
                            . '}, attributes), escaped: jade.merge(' . $val->escaped . ', escaped, true)');
                    } else {
                        array_push($this->buf, 'attributes: {' . $val->buf . '}, escaped: ' . $val->escaped);
                    }
                }

                if ($args) {
                    array_push($this->buf, '}, ' . $args . ');');
                } else {
                    array_push($this->buf, '});');
                }

            } else {
                array_push($this->buf, $name . '(' . $args . ');');
            }
            if ($pp) array_push($this->buf, "jade.indent.pop();");
        } else {
            array_push($this->buf, 'var ' . $name . ' = function(' . $args . '){');
            array_push($this->buf, 'var block = this.block, attributes = this.attributes || {}, escaped = this.escaped || {};');
            $this->parentIndents++;
            $this->inMixin = true;
            $this->visit($block);
            $this->inMixin = false;
            $this->parentIndents--;
            array_push($this->buf, '};');
        }
    }

    /**
     * Visit `tag` buffering tag markup, generating
     * attributes, visiting the `tag`'s code and block.
     *
     * @param Tag $tag
     * @api public
     */

    public function visitTag($tag) {
        $this->indents++;
        $name = get_class($tag);
        $pp = $this->pp;

        $bufferName = function () use (&$tag, &$name) {
            if ($tag->buffer) $this->bufferExpression($name);
            else $this->buffer($name);
        };

        if (!$this->hasCompiledTag) {
            if (!$this->hasCompiledDoctype && 'html' == $name) {
                $this->visitDoctype();
            }
            $this->hasCompiledTag = true;
        }

        // pretty print
        if ($pp && !$tag->isInline())
            $this->prettyIndent(0, true);

        if ((!in_array(strtolower($name), $this->selfClosing) || $tag->selfClosing) && !$this->xml) {
            $this->buffer('<');
            $bufferName();
            $this->visitAttributes($tag->attributes);
            $this->terse
                ? $this->buffer('>')
                : $this->buffer('/>');
        } else {
            // Optimize attributes buffering
            if (sizeof($tag->attributes)) {
                $this->buffer('<');
                $bufferName();
                if (sizeof($tag->attributes)) $this->visitAttributes($tag->attributes);
                $this->buffer('>');
            } else {
                $this->buffer('<');
                $bufferName();
                $this->buffer('>');
            }
            if ($tag->code) $this->visitCode($tag->code);
            $this->escape = 'pre' == $tag->name;
            $this->visit($tag->block);

            // pretty print
            if ($pp && !$tag->isInline() && 'pre' != $tag->name && !$tag->canInline())
                $this->prettyIndent(0, true);

            $this->buffer('</');
            $bufferName();
            $this->buffer('>');
        }
        $this->indents--;
    }

    /**
     * Visit `filter`, throwing when the filter does not exist.
     *
     * @param Filter $filter
     */

    public function visitFilter($filter){
        $text = join(array_map(function($node){ return $node->value; }
            ,$filter->block->nodes),"\n");
        $filter->attrs = $filter->attrs || new \stdClass();
        $filter->attrs->filename = $this->options->filename;
        $this->buffer($this->filters($filter->name, $text, $filter->attrs), true);
    }

    /**
     * Visit `text` node.
     *
     * @param Text $text
     * @api public
     */

    public function visitText($text){
        $this->buffer($text->val, true);
    }

    /**
     * Visit a `comment`, only buffering when the buffer flag is set.
     *
     * @param Comment $comment
     * @api public
     */

    public function visitComment($comment){
        if (!$comment->buffer) return;
        if ($this->pp) $this->prettyIndent(1, true);
        $this->buffer('<!--' . $comment->val . '-->');
    }

    /**
     * Visit a `BlockComment`.
     *
     * @param Comment $comment
     * @api public
     */
    public function visitBlockComment($comment) {
        if (!$comment->buffer) return;
        if ($this->pp) $this->prettyIndent(1, true);
        if (0 === strpos(trim($comment->val), 'if')) {
            $this->buffer('<!--[' . trim($comment->val) . ']>');
            $this->visit($comment->block);
            if ($this->pp) $this->prettyIndent(1, true);
            $this->buffer('<![endif]-->');
        } else {
            $this->buffer('<!--' . $comment->val);
            $this->visit($comment->block);
            if ($this->pp) $this->prettyIndent(1, true);
            $this->buffer('-->');
        }
    }

    /**
     * Visit `code`, respecting buffer / escape flags.
     * If the code is followed by a block, wrap it in
     * a self-calling function.
     *
     * @param Code $code
     * @api public
     */

    public function visitCode($code){
        // Wrap code blocks with {}.
        // we only wrap unbuffered code blocks ATM
        // since they are usually flow control

        // Buffer code
        if ($code->buffer) {
            $val = ltrim($code->val);
            $val = 'null == (jade.interp = ' . $val . ') ? "" : jade.interp';
            if ($code->escape) $val = 'jade.escape(' . $val . ')';
            $this->bufferExpression($val);
        } else {
            array_push($this->buf, $code->val);
        }

        // Block support
        if ($code->block) {
            if (!$code->buffer) array_push($this->buf, '{');
            $this->visit($code->block);
            if (!$code->buffer) array_push($this->buf, '}');
        }
    }

    /**
     * Visit `each` block.
     *
     * @param Each $each
     * @api public
     */

    public function visitEach($each){
        array_push($this->buf, ''
            . '// iterate ' . $each->obj . '\n'
            . ';(function(){\n'
            . '  var $$obj = ' . $each->obj . ';\n'
            . '  if (\'number\' == typeof $$obj.length) {\n');

        if ($each->alternative) {
            array_push($this->buf, '  if ($$obj.length) {');
        }

        array_push($this->buf, ''
            . '    for (var ' . $each->key . ' = 0, $$l = $$obj.length; ' . $each->key . ' < $$l; ' . $each->key . '++) {\n'
            . '      var ' . $each->val . ' = $$obj[' . $each->key . '];\n');

        $this->visit($each->block);

        array_push($this->buf, '    }\n');

        if ($each->alternative) {
            array_push($this->buf, '  } else {');
            $this->visit($each->alternative);
            array_push($this->buf, '  }');
        }

        array_push($this->buf, ''
            . '  } else {\n'
            . '    var $$l = 0;\n'
            . '    for (var ' . $each->key . ' in $$obj) {\n'
            . '      $$l++;'
            . '      var ' . $each->val . ' = $$obj[' . $each->key . '];\n');

        $this->visit($each->block);

        array_push($this->buf, '    }\n');
        if ($each->alternative) {
            array_push($this->buf, '    if ($$l === 0) {');
            $this->visit($each->alternative);
            array_push($this->buf, '    }');
        }
        array_push($this->buf, '  }\n}).call(this);\n');
    }

    /**
     * Visit `attrs`.
     *
     * @param Array $attrs
     * @api public
     */

    public function visitAttributes($attrs) {
        $val = $this->attrs($attrs);
        if ($val->inherits) {
            $this->bufferExpression("jade.attrs(jade.merge({ " . $val->buf .
                " }, attributes), jade.merge(" . $val->escaped . ", escaped, true))");
        } else if ($val->constant) {
            $this->buffer($this->runtime->attrs($this->toConstant('{' . $val->buf . '}'), json_decode($val->escaped)));
        } else {
            $this->bufferExpression("jade.attrs({ " . $val->buf . " }, " . $val->escaped . ")");
        }
    }

    /**
     * Compile attributes.
     */

    public function attrs($attrs){
        $buf = [];
        $classes = [];
        $escaped = [];
        $constant = array_walk($attrs, function($attr){ return $this->isConstant($attr->val);});
        $inherits = false;

        if ($this->terse) array_push($buf, 'terse: true');

        foreach($attrs as $attr) {
            if ($attr->name == 'attributes') return $inherits = true;
            $escaped[$attr->name] = $attr->escaped;
            if ($attr->name == 'class') {
                array_push($classes, '(' . $attr->val . ')');
            } else {
                $pair = "'" . $attr->name . "':(" . $attr->val . ')';
                array_push($buf, $pair);
            }
        }

        if (sizeof($classes)) {
            array_push($buf, '"class": [' . join($classes,',') . ']');
        }

        return (object)[
            "buf" => join($buf,', '),
            "escaped" => json_encode($escaped),
            "inherits" => $inherits,
            "constant" => $constant
        ];
    }
    private function parseJSExpression($rest) {
        return $this->characterParser->parseMax($rest);
    }

    private function isConstant($src) {
        return true;
    }

    private function filters($name, $text, $attrs) {
    }

    private function toConstant($string) {
        return '';
    }
}