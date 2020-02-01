<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cache\FileCache;
use Kirby\Cms\Field;
use Kirby\Database\Database;
use Kirby\Database\Db;
use Kirby\Toolkit\A;
use Kirby\Toolkit\F;

final class AutoIDDatabase
{
    /** @var self */
    private static $singleton;

    /** @var array */
    private $options;

    /** @var Database */
    private $database;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'template' => realpath(__DIR__ . '/../') . '/autoid-v2-1-0.sqlite',
            'target' => self::cacheFolder() . '/autoid-v2-1-0.sqlite',
        ], $options);

        $target = $this->options['target'];
        if (! F::exists($target)) {
            F::copy($this->options['template'], $target);
        }

        $this->database = Db::connect([
            'type' => 'sqlite',
            'database' => $target,
        ]);
    }

    public function database(): Database
    {
        return $this->database;
    }

    public function count(): int
    {
        return count($this->database->query('SELECT rowid FROM AUTOID'));
    }

    public function find($autoid): ?AutoIDItem
    {
        if (is_a($autoid, Field::class)) {
            $autoid = (string) $autoid->value();
        }

        foreach ($this->database->query("SELECT * FROM AUTOID WHERE autoid = '${autoid}'") as $obj) {
            return new AutoIDItem($obj);
        }
        return null;
    }

    public function findByID($objectid): ?AutoIDItem
    {
        if (is_a($objectid, Field::class)) {
            $objectid = (string) $objectid->value();
        }

        list($page, $filename, $structure) = $this->pageFilenameFromPath($objectid);

        foreach ($this->database->query("SELECT * FROM AUTOID WHERE page = '${page}' AND filename = '${filename}' AND structure = '${structure}'") as $obj) {
            return new AutoIDItem($obj);
        }
        return null;
    }

    public function exists($autoid): bool
    {
        if (is_a($autoid, Field::class)) {
            $autoid = (string) $autoid->value();
        }

        return $this->find($autoid) !== null;
    }

    public function modified($autoid): ?int
    {
        if (is_array($autoid)) {
            return $this->modifiedByArray($autoid);
        }

        if (is_a($autoid, Field::class)) {
            $autoid = (string) $autoid->value();
        }

        $find = $this->find($autoid);
        return $find ? $find->modified() : null;
    }

    public function modifiedByArray(array $autoids): ?int
    {
        $list = implode(', ', array_map(static function ($autoid) {
            return "'${autoid}'";
        }, $autoids));
        foreach ($this->database->query("SELECT MAX(modified) as maxmod FROM AUTOID WHERE autoid IN (${list})") as $obj) {
            return intval($obj->maxmod);
        }
        return null;
    }

    public function insertOrUpdate(AutoIDItem $item): void
    {
        if (! $item) {
            return;
        }

        // remove all with same page AND file props (even if empty)
        $this->deleteByID($item->id());

        // enter a new single entry
        $this->database->query("
            INSERT INTO AUTOID
            (autoid, modified, page, filename, structure, kind)
            VALUES
            ('{$item->autoid}', {$item->modified}, '{$item->page}', '{$item->filename}', '{$item->structure}', '{$item->kind}')
        ");
    }

    public function delete($autoid): void
    {
        if (is_a($autoid, AutoIDItem::class)) {
            $autoid = (string) $autoid->autoid();
        } elseif (is_a($autoid, Field::class)) {
            $autoid = (string) $autoid->value();
        }

        if (! is_string($autoid)) {
            return;
        }

        if (strlen(trim($autoid)) === 0) {
            return;
        }

        $this->database->query("DELETE FROM AUTOID WHERE autoid = '${autoid}'");
    }

    public function deleteByID($objectid): void
    {
        if (is_a($objectid, Field::class)) {
            $objectid = (string) $objectid->value();
        }

        list($page, $filename, $structure) = $this->pageFilenameFromPath($objectid);

        if (strlen($structure) > 0) {
            // remove structure by autoid since path to object is not unique
            $this->delete($structure);
        } else {
            $this->database->query("DELETE FROM AUTOID WHERE page = '${page}' AND filename = '${filename}'");
        }
    }

    public function flush(): void
    {
        $this->database->query("DELETE FROM AUTOID WHERE autoid != ''");
    }

    private function pageFilenameFromPath(string $objectid): array
    {
        $page = '';
        $filename = '';
        $structure = '';
        if (pathinfo($objectid, PATHINFO_EXTENSION)) {
            $pathinfo = pathinfo($objectid);
            $page = $pathinfo['dirname'];
            $filename = $pathinfo['basename'];
        } else {
            $pathinfo = pathinfo($objectid);
            $page = $pathinfo['dirname'] === '.' ? $pathinfo['basename'] : $pathinfo['dirname'] . '/' . $pathinfo['basename'];
            $structure = strpos($page, '#') !== false ? explode('#', $page)[1] : '';
        }
        return [$page, $filename, $structure];
    }

    public static function singleton(array $options = []): self
    {
        if (self::$singleton) {
            return self::$singleton;
        }
        self::$singleton = new self($options);
        return self::$singleton;
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
