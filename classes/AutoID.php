<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cms\Field;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Toolkit\Iterator;

final class AutoID
{
    public const FIELDNAME = 'autoid';
    public const GENERATE = 'PleaseGenerateUnusedAutoID'; // not like any random id

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

    public static function index(bool $force = false): int
    {
        $indexed = 0;
        if (AutoIDDatabase::singleton()->count() === 0 || $force) {
            set_time_limit(0);
            // site
            if (self::push(site())) {
                $indexed++;
            }
            // all but drafts
            foreach (site()->pages()->index() as $page) {
                if (self::push($page)) {
                    $indexed++;
                }
            }
        }
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

    public static function remove($object): void
    {
        $autoid = $object;
        if (is_a($object, Page::class) ||
            is_a($object, File::class)
        ) {
            $autoid = $object->{self::FIELDNAME}();
        }

        AutoIDDatabase::singleton()->delete($autoid);
    }

    public static function flush(): void
    {
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

        if (is_a($autoid, Page::class) || is_a($autoid, File::class)) {
            if ($autoid->{AutoID::FIELDNAME}()->isNotEmpty()) {
                return self::modified($autoid->{AutoID::FIELDNAME}());
            }
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
