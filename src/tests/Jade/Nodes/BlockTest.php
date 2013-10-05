<?php
namespace Jade\Tests\Jade\Nodes;


use Jade\Nodes\Block;
use Jade\Nodes\Tag;

class BlockTest extends \PHPUnit_Framework_TestCase {
    public function testConstruct(){
        $block = new Block();
        $this->assertCount(0, $block->nodes);
        $this->assertTrue($block->isEmpty());
    }

    public function testConstructWithNode(){
        $node = new Block();
        $block = new Block($node);
        $this->assertCount(1, $block->nodes);
    }

    public function testPush(){
        $node = new Block();
        $block = new Block();
        $block->push($node);
        $this->assertCount(1, $block->nodes);
    }

    public function testIncludesBlock() {
        $node = new Block();
        $node->includeBlock = true;
        $block = new Block();
        $block->push($node);
        $this->assertEquals(1, sizeof($block->includeBlock()));
    }


    public function testYieldBlock() {
        $block = new Block();
        $node = new Block();
        $node->includeBlock = true;
        $block->push($node);
        $node = new Tag('div');
        $block->push($node);
        $node = new Block();
        $node->yield = true;
        $block->push($node);
        $node = new Tag('h1');
        $block->push($node);
        $blocks = $block->includeBlock();

        $this->assertTrue($blocks->yield);
    }
}
 