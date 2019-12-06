<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Bnomei\AutoIDItem;
use Kirby\Cms\Page;
use PHPUnit\Framework\TestCase;

final class AutoIDItemTest extends TestCase
{
    private $page;
    private $file;
    private $invalidFile;

    protected function setUp(): void
    {
        $this->page = new AutoIDItem([
            'autoid' => '123456',
            'modified' => time(),
            'page' => 'home',
            'kind' => AutoIDItem::KIND_PAGE,
        ]);

        $this->file = new AutoIDItem([
            'autoid' => '123456',
            'modified' => time(),
            'page' => 'home',
            'filename' => 'flowers.jpg',
            'kind' => AutoIDItem::KIND_FILE,
        ]);

        $this->invalidFile = new AutoIDItem([
            'autoid' => '123456',
            'modified' => time(),
            'page' => 'home',
            'filename' => 'flowersNOPE.jpg',
            'kind' => AutoIDItem::KIND_FILE,
        ]);
    }

    public function testFile()
    {
        $this->assertInstanceOf(
            \Kirby\Cms\File::class,
            $this->file->file()
        );
        $this->assertNull(
            $this->invalidFile->file()
        );
    }

    public function testPage()
    {
        $this->assertInstanceOf(
            Page::class,
            $this->page->page()
        );
    }

    public function test__construct()
    {
        $this->assertInstanceOf(
            AutoIDItem::class,
            $this->page
        );
    }

    public function testModified()
    {
        $this->assertIsInt(
            $this->file->modified()
        );
    }

    public function testId()
    {
        $this->assertEquals(
            $this->file->file()->id(),
            $this->file->id()
        );
    }

    public function test__get()
    {
        $this->assertInstanceOf(
            Page::class,
            $this->page->page()
        );
    }

    public function testIsPage()
    {
        $this->assertTrue(
            $this->page->isPage()
        );
    }

    public function testIsFile()
    {
        $this->assertTrue(
            $this->file->isFile()
        );
    }

    public function testAutoid()
    {
        $this->assertEquals(
            '123456',
            $this->file->autoid()
        );
    }

    public function testToObject()
    {
        $this->assertInstanceOf(
            Page::class,
            $this->page->toObject()
        );
        $this->assertInstanceOf(
            \Kirby\Cms\File::class,
            $this->file->toObject()
        );
    }

    public function testGet()
    {
        $this->assertInstanceOf(
            \Kirby\Cms\File::class,
            $this->file->get()
        );
    }

    public function test__debugInfo()
    {
        $this->assertIsArray(
            $this->file->__debugInfo()
        );
    }
}
