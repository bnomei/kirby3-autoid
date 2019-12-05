<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cache\FileCache;
use Kirby\Cms\Field;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Iterator;

final class AutoID
{
    public const FIELDNAME = 'autoid';

    public static function find($autoid)
    {
        // self::index(); // NOTE: would cause loop
        $find = AutoIDDatabase::singleton()->find($autoid);
        if (!$find) {
            $find = AutoIDDatabase::singleton()->findByID($autoid);
        }
        return $find ? $find->toObject() : null;
    }

    public static function findByID($objectid)
    {
        // self::index(); // NOTE: would cause loop
        $find = AutoIDDatabase::singleton()->findByID($objectid);
        return $find ? $find->toObject() : null;
    }

    public static function tinyurl($autoid)
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

    public static function modified($autoid)
    {
        if (is_string($autoid) || is_a($autoid, Field::class)) {
            return AutoIDDatabase::singleton()->modified($autoid);
        }

        if (is_array($autoid)) {
            return AutoIDDatabase::singleton()->modifiedByArray($autoid);
        }

        if (is_a($autoid, Page::class) || is_a($autoid, File::class)) {
            if ($autoid->{AutoID::FIELDNAME}()->isNotEmpty()) {
                return self::modified($autoid->{AutoID::FIELDNAME}());
            } else {
                return $autoid->modified();
            }
        }

        if ($autoid instanceof Iterator) {
            $maxModified = [];
            foreach($autoid as $obj) {
                $mod = self::modified($obj);
                if ($mod) {
                    $maxModified[] = $mod;
                }
            }
            return count($maxModified) > 0 ? max($maxModified) : null;
        }

        return null;
    }

    public static function index(bool $force = false)
    {
        if (AutoIDDatabase::singleton()->count() === 0 || $force) {
            set_time_limit(0);
            foreach (site()->pages()->index() as $page) {
                self::push($page);
            }
        }
    }

    public static function flush()
    {
        AutoIDDatabase::singleton()->flush();
    }

    public static function push($object)
    {
        if (is_a($object, Page::class)) {
            foreach ($object->files() as $file) {
                self::push($file);
            }
            self::insertOrUpdate($object);
        } elseif (is_a($object, File::class)) {
            self::insertOrUpdate($object);
        }
    }

    private static function insertOrUpdate($object)
    {
        $item = self::createItem($object);
        if ($item) {
            AutoIDDatabase::singleton()->insertOrUpdate($item);
        }
    }

    private static function createItem($object): ?AutoIDItem
    {
        $autoid = self::readAutoidOrGenerateAndUpdate($object);
        if ($autoid === null) {
            return null;
        }

        $data = [
            'autoid' => $autoid,
        ];

        if (is_a($object, Page::class)) {
            $data['page'] = $object->id();
            $data['modified'] = $object->modified();
            $data['kind'] = AutoIDItem::KIND_PAGE;
        } elseif (is_a($object, File::class)) {
            $data['page'] = $object->page()->id();
            $data['filename'] = $object->filename();
            $data['modified'] = $object->modified();
            $data['kind'] = AutoIDItem::KIND_FILE;
        }

        return new AutoIDItem($data);
    }

    public static function readAutoidOrGenerateAndUpdate($object, bool $force = false): ?string
    {
        kirby()->impersonate('kirby');

        if ($object->blueprint()->field(Autoid::FIELDNAME) === null) {
            return null;
        } elseif (!$force && $object->{self::FIELDNAME}()->isNotEmpty()) {
            return $object->{self::FIELDNAME}()->value();
        }
        $autoid = self::generate();
        if ($autoid) {
            $object->update([
                self::FIELDNAME => $autoid
            ]);
        }
        return $autoid;
    }

    public static function generate(): ?string
    {
        $generator = option('bnomei.autoid.generator');
        if (is_callable($generator)) {
            $break = intval(option('bnomei.autoid.generator.break'));
            while ($break > 0) {
                $break--;
                $newid = $generator();
                if (AutoID::find($newid) === null) {
                    return $newid;
                }
            }
        }
        return null;
    }

    public static function remove($object)
    {
        $autoid = $object;
        if (is_a($object, Page::class) ||
            is_a($object, File::class)
        ) {
            $autoid = $object->{self::FIELDNAME}();
            if ($autoid->isEmpty()) {
                return;
            }
        }

        AutoIDDatabase::singleton()->delete($autoid);
    }

    public static function unlinkTheCopy(Page $page)
    {
        kirby()->impersonate('kirby');

        foreach ($page->files() as $file) {
            if ($file->{self::FIELDNAME}()->isNotEmpty()) {
                self::readAutoidOrGenerateAndUpdate($file, true);
            }
        }
        if ($page->{self::FIELDNAME}()->isNotEmpty()) {
            self::readAutoidOrGenerateAndUpdate($page, true);
        }
    }

    public static function cacheFolder(): string
    {
        $cache = kirby()->cache('bnomei.autoid');
        if (is_a($cache, FileCache::class)) {
            return A::get($cache->options(), 'root') . '/' . A::get($cache->options(), 'prefix');
        }
        // @codeCoverageIgnoreStart
        return kirby()->roots()->cache();
        // @codeCoverageIgnoreEnd
    }
}
