<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cms\Field;
use Kirby\Cms\File;
use Kirby\Cms\FileVersion;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Toolkit\Iterator;

final class AutoID
{
    public const FIELDNAME = 'autoid';
    public const GENERATE = 'PleaseGenerateUnusedAutoID'; // not like any random id

    public static function generate(): ?string
    {
        $generator = option('bnomei.autoid.generator');
        if (is_callable($generator)) {
            $break = intval(option('bnomei.autoid.generator-break'));
            while ($break > 0) {
                $break--;
                $newid = $generator();
                if (AutoIDDatabase::singleton()->exists($newid) === false) {
                    return $newid;
                }
            }
        }
        return null;
    }

    private static $didIndexOnce;
    public static function index(bool $force = false, ?Page $root = null): int
    {
        if (static::$didIndexOnce) {
            return static::$didIndexOnce;
        }
        $timeout = intval(\option('bnomei.autoid.index.timeout'));
        $indexed = 0;
        $indexKey = $root ? $root->id() : 'site';
        $indexing = kirby()->cache('bnomei.autoid')->get($indexKey, false);
        if ($force || $indexing || AutoIDDatabase::singleton()->count() === 0) {
            kirby()->cache('bnomei.autoid')->set($indexKey, true);
            $break = time() + $timeout;
            $indexer = new AutoIDIndexer($root);
            foreach ($indexer->next() as $page) {
                if (self::push($page)) {
                    $indexed++;
                }
                if (!$force && time() >= $break) {
                    $break = true;
                    break;
                }
            }
            // if break then is still indexing
            kirby()->cache('bnomei.autoid')->set($indexKey, $break === true);
        }
        static::$didIndexOnce = $indexed;
        return $indexed;
    }

    public static function push($object, bool $overwrite = false): bool
    {
        if (! $object) {
            return false;
        }
        $process = new AutoIDProcess($object, $overwrite);
        return $process->isIndexed();
    }

    public static function remove($object, bool $recursive = false): void
    {
        if (is_string($object)) {
            $item = AutoIDDatabase::singleton()->find($object);
            $object = $item->page();
            AutoIDDatabase::singleton()->delete($object);
        }

        if (is_a($object, Page::class)) {
            AutoIDDatabase::singleton()->deleteByID($object->id());
            if ($recursive) {
                AutoIDDatabase::singleton()->deleteByDiruri($object->diruri());
            }
        }

        if (is_a($object, File::class)) {
            AutoIDDatabase::singleton()->deleteByID($object->id());
        }
    }

    public static function flush(): void
    {
        static::$didIndexOnce = null;
        kirby()->cache('bnomei.autoid')->set('site', false);
        AutoIDDatabase::singleton()->flush();
    }

    /**
     * @param $autoid
     *
     * @return array|File|Page|null
     */
    public static function find($autoid)
    {
        // self::index(); // NOTE: would cause loop
        $find = AutoIDDatabase::singleton()->find($autoid);
        if (! $find) {
            $find = AutoIDDatabase::singleton()->findByID($autoid);
        }
        if(! $find) {
            if($page = site()->index()->filterBy(self::FIELDNAME, $autoid)->first()) {
                self::push($page);
                return $page;
            }
            if($file = site()->index()->files()->filterBy(self::FIELDNAME, $autoid)->first()) {
                self::push($file);
                return $file;
            }
        }
        return $find ? $find->toObject() : null;
    }

    /**
     * @param $objectid
     *
     * @return array|File|Page|null
     */
    public static function findByID($objectid)
    {
        // self::index(); // NOTE: would cause loop
        $find = AutoIDDatabase::singleton()->findByID($objectid);
        return $find ? $find->toObject() : null;
    }

    /**
     * @param $autoid
     *
     * @return int|null
     */
    public static function modified($autoid)
    {
        if (is_string($autoid) || is_a($autoid, Field::class)) {
            return AutoIDDatabase::singleton()->modified($autoid);
        }

        if (is_array($autoid)) {
            return AutoIDDatabase::singleton()->modifiedByArray($autoid);
        }

        if (is_a($autoid, Site::class)) {
            $item = AutoIDDatabase::singleton()->findByID('$');
            if ($item) {
                return $item->modified();
            }
            return $autoid->modified();
        }

        if(is_a($autoid,  \Kirby\Cms\Site::class)){
            // site->modified() would be ALL content files
            return filemtime(site()->contentFile());
        }

        if (is_a($autoid, Page::class) ||
            is_a($autoid, File::class) ||
            is_a($autoid, FileVersion::class)) {
            /*
            // try finding without reading the file
            $item = AutoIDDatabase::singleton()->findByID($autoid->id());
            if ($item) {
                return $item->modified();
            }
            */
            // use the file timestamp since that is the fastest thing to do
            // in kirby when a the object exists
            return $autoid->modified();
        }

        if ($autoid instanceof Iterator) {
            $maxModified = [];
            foreach ($autoid as $obj) {
                $mod = self::modified($obj);
                if ($mod) {
                    $maxModified[] = $mod;
                }
            }
            return count($maxModified) > 0 ? max($maxModified) : null;
        }

        return null;
    }

    public static function tinyurl($autoid): string
    {
        $url = option('bnomei.autoid.tinyurl.url');
        if ($url && is_callable($url)) {
            $url = $url();
        }
        if ($url === kirby()->url('index')) {
            $url = rtrim($url, '/') . '/' . option('bnomei.autoid.tinyurl.folder');
        }
        return rtrim($url, '/') . '/' . $autoid;
    }
}
