<?php

/*
    TODO: silent mode for everything without a field on blueprint
    TODO: seperate index function for File
    TODO: rebuildIndex cache is public for dev

    PRIVATE
    - cache             kirby cms cache object
    - index             get index array from cache
    - rebuildIndex      start crawling
    - commitEntry       add found autoid entry to tmp array
    - pushEntries       merge found entries from tmp array with index array
    - updateIndex       set index array to cache
    - removeEntry       removes an entry from index array
    - indexPage         check single page for autoid entries
    - log               creates a log post (if possible), debug level only on option('debug') == true

    PUBLIC
    - find              find an entry
    - collection        return index array as collection
    - array             return index array as array
    - flush             clears the cache
    - tinyurl

    GENERATOR
    - cryptoRandSecure  random (from before php7)
    - getToken          random token
    - defaultGenerator  2.8 trillion alpha numeric hash
    - generator         calls config setting generator or default

    HOOK
    - addPage           will call indexPage on page
    - removePage        will call removeEntry on page
    - addFile           will call indexPage on $file->page()
    - removeFile        will call removeEntry on $file->page()
*/

namespace Bnomei;

class AutoID
{
    private static $fieldname = 'autoid'; // TODO: custom fieldname is not a good idea imho
    public static function fieldname(): string
    {
        return static::$fieldname;
    }

    private static $indexname = null;
    private static $cache = null;
    private static function cache(): \Kirby\Cache\Cache
    {
        if (!static::$cache) {
            static::$cache = kirby()->cache('bnomei.autoid');
        }
        // create new index table on new version of plugin
        if (!static::$indexname) {
            static::$indexname = 'index'.str_replace('.', '', kirby()->plugin('bnomei/autoid')->version()[0]);
        }
        return static::$cache;
    }

    // get current index, rebuild if cache does not exist
    private static function index(): array
    {
        $index = static::cache()->get(static::$indexname);
        if (!$index && !is_array($index)) {
            $indexed = static::rebuildIndex();
        }
        return static::cache()->get(static::$indexname);
    }

    public static function rebuildIndex(bool $force = false): int
    {
        $i = static::cache()->get(static::$indexname);
        if (!$force && $i) {
            static::log('rebuildIndex:exists');
            return count($i);
        }
        static::log('rebuildIndex:before');
        static::cache()->flush();
        static::cache()->set(static::$indexname, []);
        $indexed = 0;
        $entries = [];
        $root = option('bnomei.autoid.index');
        if ($root && is_callable($root)) {
            $root = $root();
        } else {
            $root = kirby()->pages()->index();
        }
        // NOTE: this operation will be very slow if index grows
        foreach ($root as $page) {
            set_time_limit(0);
            $newEntries = static::indexPage($page);
            $indexed += count($newEntries);
            $entries = array_merge($entries, $newEntries);
        }
        // update cache in one push to improve performance
        // if cache is filebased
        static::pushEntries($entries);
        static::log('rebuildIndex:after');
        return $indexed;
    }

    public const ID = 'pageid';
    public const STRUCTURE = 'structure';
    public const FILENAME = 'filename';
    public const MODIFIED = 'modified';
    public const AUTOID = 'autoid';
    public const TYPE = 'type';

    // append autoid data to in memory array
    private static function commitEntry(
        array $tmp,
        string $autoid,
        string $pageId,
        string $structureFieldname = null,
        string $filename = null,
        int $modified = null
    ): array {
        $type = 'page';
        if ($structureFieldname) {
            $type = 'structure';
        }
        if ($filename) {
            $type = 'file';
        }

        $tmp[$autoid] = [
            self::ID => $pageId,
            self::STRUCTURE => $structureFieldname,
            self::FILENAME => $filename,
            self::MODIFIED => $modified,
            self::AUTOID => $autoid,
            self::TYPE => $type,
        ];
        static::log('commitEntry', 'debug', $tmp[$autoid]);
        return $tmp;
    }

    // write array of autoid data to cache
    private static function pushEntries($entries): bool
    {
        $index = array_merge(static::index(), $entries);
        ksort($index); // will speed up getting data by a fraction
        return static::updateIndex($index);
    }

    private static function updateIndex(array $index): bool
    {
        static::$collection = null;
        static::$array = null;
        return static::cache()->set(static::$indexname, $index);
    }

    private static function removeEntry($autoid): bool
    {
        $index = static::cache()->get(static::$indexname);
        if ($index && is_array($index) && \Kirby\Toolkit\A::get($index, $autoid)) {
            static::log('removeEntry', 'debug', ['autoid' => $autoid]);
            unset($index[$autoid]);
            static::$collection = null;
            static::$array = null;
            return static::cache()->set(static::$indexname, $index);
        }
        return false;
    }

    private static function indexPage(\Kirby\Cms\Page $page, array $commits = [], bool $reset = false): array
    {
        static::log('indexPage:before', 'debug', ['page.id' => $page->id()]);
        // kirby()->impersonate('kirby');

        $commitsPage = [];
        $commitsFiles = [];
        $updatePage = []; // array to update Page-Object with
        $updateFile = []; // array to update Page-Object with

        // TODO: silent mode would just try reading $static::fieldname() AND check all structures
        foreach ($page->blueprint()->fields() as $field) {
            if (option('bnomei.autoid.index.pages') && array_key_exists('name', $field) && $field['name'] == static::fieldname()) {
                $f = $field['name'];
                $autoidField = $page->$f();
                if ($autoidField->isEmpty() || $reset) {
                    $autoid = static::generator();
                    $updatePage = array_merge($updatePage, [
                        static::fieldname() => $autoid
                    ]);
                    $commitsPage = static::commitEntry($commitsPage, $autoid, $page->id(), null, null, $page->modified('U', 'date'));
                } else {
                    $commitsPage = static::commitEntry($commitsPage, $autoidField->value(), $page->id(), null, null, $page->modified('U', 'date'));
                }
            } elseif (option('bnomei.autoid.index.structures') && array_key_exists('type', $field) && array_key_exists('name', $field) && $field['type'] == 'structure') {
                // make copy as array so can update
                $f = $field['name'];
                $structureFieldValue = $page->$f()->value();
                $data = \Yaml::decode($structureFieldValue);
                $copy = $data; // this is a copy since its an array
                $hasChange = false;
                for ($d=0; $d<count($data); $d++) {
                    $structureObject = $data[$d];
                    // TODO: is support for nested structures needed?
                    if (is_array($structureObject)) {
                        if (array_key_exists(static::fieldname(), $structureObject)) {
                            $value = \Kirby\Toolkit\A::get($structureObject, static::fieldname());
                            if (empty($value) || $reset) {
                                // update structure in copy
                                $hasChange = true;
                                $autoid = static::generator();
                                $copy[$d][static::fieldname()] = $autoid;
                                $commitsPage = static::commitEntry($commitsPage, $autoid, $page->id(), $field['name'], null, $page->modified('U', 'date'));
                            } else {
                                $commitsPage = static::commitEntry($commitsPage, $value, $page->id(), $field['name'], null, $page->modified('U', 'date'));
                            }
                        }
                    }
                }

                if ($hasChange) {
                    $updatePage = array_merge($updatePage, [
                        $field['name'] => \Yaml::encode($copy),
                    ]);
                }
            }
        }

        // loop through each File of page and check blueprint and fields

        if (option('bnomei.autoid.index.files')) {
            foreach ($page->files() as $file) {
                // TODO: silent mode would just try reading $static::fieldname() AND check all structures
                foreach ($file->blueprint()->fields() as $field) {
                    if (array_key_exists('name', $field) && $field['name'] == static::fieldname()) {
                        $f = self::fieldname();
                        $autoidField = $file->$f();
                        if ($autoidField->isEmpty() || $reset) {
                            $autoid = static::generator();
                            $updateFile = [
                                static::fieldname() => $autoid
                            ];

                            try {
                                kirby()->impersonate('kirby');
                                $file->update($updateFile);
                                $commitsFiles = static::commitEntry($commitsFiles, $autoid, $page->id(), null, $file->filename(), $file->modified('U', 'date'));  // TODO: name or filename?
                            } catch (Exception $e) {
                                static::log($e->getMessage(), 'error', ['page.id' => $page->id(), 'filename' => $file->filename()]);
                            }
                        } else {
                            $commitsFiles = static::commitEntry($commitsFiles, $autoidField->value(), $page->id(), null, $file->filename(), $file->modified('U', 'date'));  // TODO: name or filename?
                        }
                    }
                }
            }
        }

        try {
            if (count($updatePage) > 0) {
                kirby()->impersonate('kirby');
                $page->update($updatePage);
            }
        } catch (Exception $e) {
            static::log($e->getMessage(), 'error', [$page->id()]);
            $commitsPage = []; // reset since failed
            static::log('commits to page reset', 'debug', ['page.id' => $page->id()]);
        }

        static::log('indexPage:after', 'debug', ['page.id' => $page->id()]);
        return array_merge($commits, $commitsPage, $commitsFiles);
    }

    private static function log(string $msg = '', string $level = 'info', array $context = []):bool
    {
        $log = option('bnomei.autoid.log');
        if ($log && is_callable($log)) {
            if (!option('debug') && $level == 'debug') {
                // skip but...
                return true;
            } else {
                return $log($msg, $level, $context);
            }
        }
        return false;
    }

    /****************************************************************
     * PUBLIC find, collection
     */

    public static function find($autoid)
    {
        if ($entry = \Kirby\Toolkit\A::get(static::index(), $autoid)) {
            if ($page = \page(\Kirby\Toolkit\A::get($entry, self::ID))) {
                if ($structureField = \Kirby\Toolkit\A::get($entry, self::STRUCTURE)) {
                    foreach ($page->$structureField()->toStructure() as $structureObject) {
                        $field = static::fieldname();
                        $sf = $structureObject->$field();
                        if ($sf && $sf->value() == $autoid) {
                            static::log('found structure', 'debug', ['autoid' => $autoid]);
                            return $structureObject;
                        }
                    }
                } elseif ($filename = \Kirby\Toolkit\A::get($entry, self::FILENAME)) {
                    static::log('found file', 'debug', ['autoid' => $autoid]);
                    return $page->file($filename);
                }
                static::log('found page', 'debug', ['autoid' => $autoid]);
                return $page;
            }
        }
        static::log('autoid not found', 'warning', ['autoid' => $autoid]);
        return null;
    }

    private static $collection = null;
    public static function collection()
    {
        if (!static::$collection) {
            static::$collection = new \Kirby\Toolkit\Collection(static::index());
        }
        return static::$collection;
    }

    private static $array = null;
    public static function array()
    {
        if (!static::$array) {
            static::$array = static::index();
        }
        return static::$array;
    }

    public static function flush()
    {
        return static::cache()->flush();
    }

    public static function tinyurl($autoid)
    {
        $url = option('bnomei.autoid.tinyurl.url');
        if ($url && is_callable($url)) {
            $url = $url();
        }
        if ($url == kirby()->url('index')) {
            $url = rtrim($url, '/') . '/' . option('bnomei.autoid.tinyurl.folder');
        }
        return rtrim($url, '/') . '/' . $autoid;
    }

    /****************************************************************
     * PUBLIC generator
     */

    // http://stackoverflow.com/questions/1846202/php-how-to-generate-a-random-unique-alphanumeric-string/13733588#13733588
    public static function cryptoRandSecure($min, $max)
    {
        $range = $max - $min;
        if ($range < 1) {
            return $min;
        } // not so random...
        $log = ceil(log($range, 2));
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = \hexdec(\bin2hex(\openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd > $range);
        return $min + $rnd;
    }

    public static function getToken($length = 40, $withLower = true, $withUpper = true, $withNumbers = true)
    {
        $token = "";
        $codeAlphabet = "";
        if ($withUpper) {
            $codeAlphabet .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        }
        if ($withLower) {
            $codeAlphabet .= "abcdefghijklmnopqrstuvwxyz";
        }
        if ($withNumbers) {
            $codeAlphabet .= "0123456789";
        }
        $max = strlen($codeAlphabet); // edited
        for ($i=0; $i < $length; $i++) {
            $token .= $codeAlphabet[self::cryptoRandSecure(0, $max-1)];
        }
        return $token;
    }

    public static function defaultGenerator(): string
    {
        // alphanumeric: 8 chars, lowercase and numbers
        // (26 + 10) ^ 8 = 2.821.109.907.456 = ~ 2.8 trillion possibilities
        return static::getToken(8, true, false, true);
    }

    public static function generator(string $seed = null): string
    {
        $hash = null;
        $generator = kirby()->option('bnomei.autoid.generator');
        if ($generator && is_callable($generator)) {
            $hash = $generator($seed);
        } else {
            $hash = static::defaultGenerator();
        }
        // if custom generator is not unique enough give it a few tries
        $break = intval(option('bnomei.autoid.generator.break'));
        while ($break > 0 && \Kirby\Toolkit\A::get(static::index(), $hash) != null) {
            $hash = static::generator($seed);
            $break--;
            if ($break == 0) {
                static::log('generator.break hit. fallback to defaultGenerator.', 'warning', ['break' => intval(option('bnomei.autoid.generator.break'))]);
                $hash = static::defaultGenerator();
            }
        }
        return $hash;
    }

    /****************************************************************
     * PUBLIC add/remove
     */

    public static function addPage(\Kirby\Cms\Page $page): bool
    {
        return static::pushEntries(static::indexPage($page));
    }

    public static function removePage(\Kirby\Cms\Page $page): bool
    {
        $f = static::fieldname();
        $field = $page->$f();
        if ($field->isNotEmpty()) {
            return static::removeEntry($field->value());
        }
        return false;
    }
    
    public static function resetPage(\Kirby\Cms\Page $page): bool
    {
        return static::pushEntries(static::indexPage($page, [], true))
    }

    public static function addFile(\Kirby\Cms\File $file): bool
    {
        $p = $file->page();
        if (!$p) { // https://github.com/bnomei/kirby3-autoid/pull/21
            return false;
        }
        return static::pushEntries(static::indexPage($p));
    }

    public static function removeFile(\Kirby\Cms\File $file): bool
    {
        $f = static::fieldname();
        $field = $file->$f();
        if ($field->isNotEmpty()) {
            return static::removeEntry($field->value());
        }
        return false;
    }
}
