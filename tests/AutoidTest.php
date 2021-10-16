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
        if ($depth % 2 === 0) {
            $page = $page->changeStatus('unlisted');
        } else {
            $page = $page->changeStatus('listed');
        }
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

    public function randomPageWithChildren(): ?Page
    {
        $randomPage = null;
        $hasChildren = false;
        while (!$hasChildren) {
            $randomPage = $this->randomPage();
            $hasChildren = $randomPage->hasChildren();
        }

        return $randomPage;
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
        $count = AutoID::index(true);

        $this->assertTrue(
            $count > 0
        );
        $this->assertTrue(
            AutoIDDatabase::singleton()->count() > 0
        );
        $this->assertEquals(
            site()->index()->count() + 1, // + site
            $count
        );
    }
    public function testIndexForced()
    {
        AutoID::flush();
        $all = AutoID::index(true); // all

        $count = AutoID::index(); // none left
        $this->assertTrue(
            $count === $all
        );
    }

    public function testModified()
    {
//        AutoID::flush();
//        AutoID::index(true);

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
        // AutoID::index(true);

        /* @var $page Page */
        $page = $this->randomPage();

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

    }

    public function testFindByIDUnused()
    {
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

        // find page with subpages
        $page = null;
        while(!$page || $page->index()->count() === 0) {
            $page = $this->randomPage();
        }
        // $autoid = $page->autoid()->value();
        // and copy its children as well
        $dup = $page->duplicate('test-duplicate', ['children' => true]);
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
//        AutoID::index(true);

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
//        AutoID::flush();
//        AutoID::index(true);

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

        AutoID::flush();
        AutoID::index(true);
    }

    public function testChangeSlugWillRedindexChildren()
    {
//        AutoID::flush();
//        AutoID::index(true);

        $randomPage = null;
        while (! $randomPage ) {
            $p = $this->randomPage();
            if ($p->hasChildren()) {
                $randomPage = $p;
            }
        }
        $randomPageChild = $randomPage->children()->first();
        $randomPageChildSlug = $randomPageChild->slug();
        $randomPageChildAutoid = $randomPageChild->autoid()->value();

        $newSlug = md5((string) time());
        $oldSlug = $randomPage->slug();

        kirby()->impersonate('kirby');
        $randomPage = $randomPage->changeSlug($newSlug);
        $randomPageChild = $randomPage->children()->filter(function($child) use ($randomPageChildSlug) {
            return $child->slug() === $randomPageChildSlug;
        })->first();

        $randomPageFound = \autoid($randomPage->autoid()->value());
        $this->assertNotNull($randomPageFound);

        $randomPageChildFound = \autoid($randomPageChildAutoid);
        $this->assertNotNull($randomPageChildFound);

        $this->assertStringContainsString(
            $randomPageFound->diruri(),
            $randomPageChild->diruri()
        );

        // revert
        $randomPageFound->changeSlug($oldSlug);

        AutoID::flush();
        AutoID::index(true);
    }

    public function testFindByTemplate()
    {
        //AutoID::flush();
        //AutoID::index(true);

        $randomPage = $this->randomPageWithChildren();
        $collection = AutoIDDatabase::singleton()->findByTemplate(
            'autoidtest',
            $randomPage->id()
        );
        $this->assertTrue($collection->count() > 0);
        $this->assertEquals($randomPage->index()->not($randomPage)->count(), $collection->count());

        $randomPage = $this->randomPageWithChildren();
        $collection = $randomPage->searchForTemplate('autoidtest');
        $this->assertTrue($collection->count() > 0);
        $this->assertEquals($randomPage->index()->not($randomPage)->count(), $collection->count());

        $collection = site()->searchForTemplate('autoidtest');
        $this->assertTrue($collection->count() > 0);
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
        $this->assertMatchesRegularExpression('/^.{8}$/', $autoid);
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
        $this->assertFileDoesNotExist($dbfilePath);
        new AutoIDDatabase(); // create db
        $this->assertFileExists($dbfilePath);
    }
}
