<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Bnomei\AutoID;
use Bnomei\AutoIDDatabase;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\StructureObject;
use Kirby\Toolkit\F;
use Kirby\Toolkit\Str;
use PHPUnit\Framework\TestCase;

final class AutoidTest extends TestCase
{
    private $depth;
    private $filepath;

    public function setUp(): void
    {
        $this->filepath = __DIR__ . '/flowers.jpg';

        // AutoID::index(true);
    }

    public function setUpPages(): void
    {
        AutoID::flush();

        $this->depth = 3;

        if (site()->pages()->children()->notTemplate('home')->count() === 0) {
            for ($i = 0; $i < $this->depth; $i++) {
                $this->createPage(site(), $i, $this->depth);
            }
        }
        $this->assertTrue(true);
    }

    public function createPage($parent, int $idx, int $depth = 3): Page
    {
        $id = 'Test ' . abs(crc32(microtime() . $idx . $depth));
        /* @var $page Page */
        kirby()->impersonate('kirby');
        $page = $parent->createChild([
            'slug' => Str::slug($id),
            'template' => 'autoidtest',
            'content' => [
                'title' => $id,
                'level1' => Yaml::encode([
                    [
                        'text' => 'level1 0 ' . $id,
                        'level2' => [
                            ['text' => 'level1 0 level2 0 ' . $id],
                            ['text' => 'level1 0 level2 1 ' . $id]
                        ],
                    ],
                    [
                        'text' => 'level1 1 ' . $id,
                        'level2' => [
                            ['text' => 'level1 1 level2 0 ' . $id],
                            ['text' => 'level1 1 level2 1 ' . $id],
                            ['text' => 'level1 1 level2 2 ' . $id],
                        ],
                    ],
                ]),
            ],
        ]);
        $page->createFile([
            'source' => $this->filepath,
            'template' => 'autoidimage'
        ]);
        $page = $page->changeStatus('unlisted');
        if ($depth > 0) {
            $depth--;
            for ($i = 0; $i < $depth; $i++) {
                $this->createPage($page, $i, $depth);
            }
        }

        return $page;
    }

    public function randomPage(): ?Page
    {
        return site()->pages()->index()->notTemplate('home')->shuffle()->first();
    }

    public function randomFile(): ?File
    {
        return site()->pages()->index()->notTemplate('home')->files()->shuffle()->first();
    }

    public function tearDownPages(): void
    {
        kirby()->impersonate('kirby');
        /* @var $page Page */
        foreach (site()->pages()->index()->notTemplate('home') as $page) {
            $page->delete(true);
        }
        AutoID::flush();
    }

    public function testFlush()
    {
        AutoID::flush();

        $this->assertTrue(
            AutoIDDatabase::singleton()->count() === 0
        );
    }

    public function testIndex()
    {
        AutoID::flush();
        $count = AutoID::index();

        $this->assertTrue(
            $count > 0
        );
        $this->assertTrue(
            AutoIDDatabase::singleton()->count() > 0
        );
    }

    public function testModified()
    {
        AutoID::flush();
        AutoID::index();

        $page = $this->randomPage();

        $this->assertEquals(
            $page->modified(),
            modified($page->autoid()->value())
        );

        $pageA = $this->randomPage();
        $pageB = $this->randomPage();
        $this->assertEquals(
            max($pageA->modified(), $pageB->modified()),
            modified([
                $pageA->autoid()->value(),
                $pageB->autoid()->value()
            ])
        );

        $allCollection = site()->pages()->index()->notTemplate('home');
        $maxModified = null;
        foreach ($allCollection as $pall) {
            if (!$maxModified || $maxModified < $pall->modified()) {
                $maxModified = $pall->modified();
            }
        }
        $this->assertEquals(
            $maxModified,
            modified($allCollection)
        );
    }

    public function testFindByID()
    {
        AutoID::index(true);

        /* @var $page Page */
        $page = $this->randomPage();
        var_dump($page->id());
        var_dump(AutoID::findByID($page->id()));

        $this->assertTrue(
            AutoID::findByID($page->id()) === $page
        );

        $this->assertTrue(
            \autoid($page->id()) === $page
        );
        $this->assertTrue(
            \autoid($page) === $page
        );

        /* @var $page File */
        $file = $this->randomFile();
        $this->assertTrue(
            AutoID::findByID($file->id()) === $file
        );
        $this->assertTrue(
            \autoid($file->id()) === $file
        );
        $this->assertTrue(
            \autoid($file) === $file
        );

        $unusedID = \autoid();
        $this->assertTrue(
            AutoID::find($unusedID) === null
        );
    }

    public function testTinyUrl()
    {
        /* @var $page Page */
        $page = $this->randomPage();
        $this->assertStringEndsWith(
            '/x/' . $page->{AutoID::FIELDNAME}()->value(),
            $page->tinyUrl()
        );
    }

    public function testDB()
    {
        $this->assertNotNull(
            AutoIDDatabase::singleton()->database()
        );

        $pageA = $this->randomPage();
        $pageB = $pageA->autoid()->fromAutoID();
        $this->assertEquals(
            $pageA,
            $pageB
        );

        $autoidAField = $pageA->autoid();
        $pageC = AutoIDDatabase::singleton()->find($autoidAField)->page();
        $this->assertEquals(
            $pageA,
            $pageC
        );

        $this->assertTrue(
            AutoIDDatabase::singleton()->exists($autoidAField)
        );

        $autoidAItem = AutoIDDatabase::singleton()->find($autoidAField);
        AutoIDDatabase::singleton()->delete($autoidAItem);

        $this->assertFalse(
            AutoIDDatabase::singleton()->exists($autoidAField)
        );

        $pageDField = $this->randomPage()->autoid();
        AutoIDDatabase::singleton()->delete($pageDField);

        AutoIDDatabase::singleton()->delete(null);
    }

    public function testDuplicate()
    {
        kirby()->impersonate('kirby');
        $page = $this->randomPage();
//        $autoid = $page->autoid()->value();
        $dup = $page->duplicate('test-duplicate');
        // will trigger duplicate:after hook
        // but call again for testing
        AutoID::push($dup, true);

        $this->assertTrue(
            $dup->autoid()->isNotEmpty()
        );
        // dub is not object post dup:after->update hook so
        // does not work with kirbys persistent pages objects
        /*
        $this->assertTrue(
            $dup->autoid()->value() !== $autoid
        );
        */

        AutoID::remove($dup);
        $dup->delete(true);
    }

    public function testSite()
    {
        AutoID::index(true);

        $taxonomies = site()->taxonomy()->yaml();
        $randIdx = rand(0, count($taxonomies)-1);
        $randomTax = $taxonomies[$randIdx];
        $randID = $randomTax['autoid'];
        $find = \autoid($randID);

        $this->assertInstanceOf(StructureObject::class, $find);
        $this->assertEquals($randID, $find->id());
        $this->assertEquals(site(), $find->parent());
        $this->assertEquals($randomTax['title'], $find->title());
    }

    public function testChangeSlugOfPage()
    {
        $randomPage = $this->randomPage();
        $autoid = $randomPage->autoid()->value();
        $oldSlug = $randomPage->slug();
        $newSlug = md5($oldSlug . time());

        kirby()->impersonate('kirby');
        $updatedPage = $randomPage->changeSlug($newSlug);
        $this->assertEquals($newSlug, $updatedPage->slug());
        $this->assertEquals($autoid, $updatedPage->autoid()->value());
        $this->assertEquals($updatedPage, autoid($autoid));

        // revert
        $updatedPage->changeSlug($oldSlug);
    }

    public function testFindByTemplate()
    {
        // AutoID::flush();
        AutoID::index(true);

        $randomPage = $this->randomPage();
        $collection = AutoIDDatabase::singleton()->findByTemplate(
            'autoidtest',
            $randomPage->id()
        );
        $this->assertEquals($randomPage->index()->not($randomPage)->count(), $collection->count());

        $randomPage = $this->randomPage();
        $collection = $randomPage->searchForTemplate('autoidtest');
        $this->assertEquals($randomPage->index()->not($randomPage)->count(), $collection->count());

        $collection = site()->searchForTemplate('autoidtest');
        $this->assertEquals(site()->index()->notTemplate('home')->count(), $collection->count());
    }

    public function testCreateAndRetrieveAutoID()
    {
        $newPage = $this->createPage(page('home'),0 ,0);

        // autoid is null since object is not the one past the update hook
        $this->assertTrue($newPage->autoid()->isEmpty());

        // value autoid in NEW pageobject is still null
        $this->assertNull(\autoid($newPage)->autoid()->value());

        // but AUTOID pagemethod should register and retrieve non the less
        $autoid = $newPage->AUTOID();
        $this->assertRegExp('/^.{8}$/', $autoid);
        $this->assertTrue(AutoIDDatabase::singleton()->exists($autoid));
        $this->assertEquals($newPage, AutoID::findByID($newPage->id()));
        $this->assertEquals($newPage, \autoid($newPage->id()));
        $this->assertEquals($newPage, \autoid($newPage));

        $newPageID = $newPage->id();
        $this->assertTrue($newPage->delete(true));
        $this->assertNull(\autoid($autoid));

        $this->assertNull(AutoID::findByID($newPageID));
    }

    public function testEdgeCases()
    {
        $this->assertNull(AutoIDDatabase::singleton()->modified(['not', 'existing', 'ids']));

        $randomPage = $this->randomPage();
        $this->assertNotNull(AutoIDDatabase::singleton()->findByID(
            new \Kirby\Cms\Field($randomPage, 'ref', $randomPage->id()) // a field with id ref
        ));

        $dbfilePath = AutoIDDatabase::singleton()->databaseFile();
        F::remove($dbfilePath);
        $this->assertFileNotExists($dbfilePath);
        new AutoIDDatabase(); // create db
        $this->assertFileExists($dbfilePath);
    }
}
