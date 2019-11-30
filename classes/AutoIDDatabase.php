<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cms\Field;
use Kirby\Database\Database;
use Kirby\Database\Db;
use Kirby\Toolkit\F;

final class AutoIDDatabase
{
    private $options;
    private $db;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'template' => realpath(__DIR__ . '/../') . '/autoid.sqlite',
            'target' => AutoID::cacheFolder() . '/autoid.sqlite',
        ], $options);

        $target = $this->options['target'];
        if (!F::exists($target)) {
            F::copy($this->options['template'], $target);
        }

        $this->db = DB::connect([
            'type' => 'sqlite',
            'database' => $target,
        ]);
    }

    public function database(): Database
    {
        return $this->db;
    }

    public function count(): int
    {
        return count($this->db->query("SELECT rowid FROM AUTOID"));
    }

    public function find($autoid): ?AutoIDItem
    {
        if (is_a($autoid, Field::class)) {
            $autoid = (string) $autoid->value();
        }

        foreach ($this->db->query("SELECT * FROM AUTOID WHERE autoid = '$autoid'") as $obj) {
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

    public function insertOrUpdate(AutoIDItem $item)
    {
        $autoid = $item->autoid();


        if ($this->find($autoid)) {
            $this->db->query("
                UPDATE AUTOID
                SET
                page = '{$item->page}'
                filename = '{$item->filename}'
                kind = '{$item->kind}'
                WHERE autoid = '$autoid'
            ");
        } else {
            $this->db->query("
                INSERT INTO AUTOID
                (autoid, modified, page, filename, kind)
                VALUES
                ('{$item->autoid}', {$item->modified}, '{$item->page}', '{$item->filename}', '{$item->kind}')
            ");
        }
    }

    public function delete($autoid)
    {
        if (is_a($autoid, AutoIDItem::class)) {
            $autoid = (string)$autoid->autoid();
        } elseif (is_a($autoid, Field::class)) {
            $autoid = (string)$autoid->value();
        }

        if (!is_string($autoid)) {
            return;
        }

        $this->db->query("DELETE FROM AUTOID WHERE autoid = '$autoid'");
    }

    public function flush()
    {
        $this->db->query("DELETE FROM AUTOID WHERE autoid != ''");
    }

    private static $singleton;

    public static function singleton(array $options = []): self
    {
        if (self::$singleton) {
            return self::$singleton;
        }
        self::$singleton = new self($options);
        return self::$singleton;
    }
}
