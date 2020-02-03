<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Cms\StructureObject;
use Kirby\Toolkit\A;
use Kirby\Toolkit\Obj;

final class AutoIDItem
{
    public const KIND_PAGE = 'Page';
    public const KIND_FILE = 'File';
    public const KIND_STRUCTUREOBJECT = 'StructureObject';

    /** @var Obj */
    private $data;

    public function __construct($data)
    {
        if (is_array($data)) {
            $data = new Obj($data);
        }
        $this->data = $data;
    }

    public function autoid(): string
    {
        return $this->data->autoid;
    }

    public function modified(): int
    {
        return intval($this->data->modified);
    }

    /**
     * @return Page|Site|null
     */
    public function page()
    {
        $id = $this->data->page;
        return $id === '$' ? site() : page($this->data->page);
    }

    public function file(): ?File
    {
        $page = $this->page();
        if ($page) {
            return $page->file($this->data->filename);
        }
        return null;
    }

    public function structureObject(): ?StructureObject
    {
        $tree = array_map(static function ($value) {
            return is_numeric($value) ? intval($value) : $value;
        }, explode(',', $this->structure));

        $root = array_shift($tree);
        $fieldArray = $this->page()->{$root}()->yaml();

        foreach ($tree as $leaf) {
            $fieldArray = A::get($fieldArray, $leaf);
        }
        return new StructureObject([
            'id' => $this->autoid(),
            'content' => $fieldArray,
            'parent' => $this->self(),
        ]);
    }

    public function isPage(): bool
    {
        return $this->data->kind === self::KIND_PAGE;
    }

    public function isFile(): bool
    {
        return $this->data->kind === self::KIND_FILE;
    }

    public function isStructureObject(): bool
    {
        return $this->data->kind === self::KIND_STRUCTUREOBJECT;
    }

    /**
     * @return array|File|Page|null
     */
    public function toObject()
    {
        if ($this->isPage()) {
            return $this->page();
        }
        if ($this->isFile()) {
            return $this->file();
        }
        if ($this->isStructureObject()) {
            return $this->structureObject();
        }
        return null;
    }

    public function id(): ?string
    {
        if ($this->isPage()) {
            return $this->data->page;
        }
        if ($this->isFile()) {
            return $this->data->page . '/' . $this->filename;
        }
        if ($this->isStructureObject()) {
            // tree is not unique post update since its sortable. use autoid as id
            return $this->data->page . '#' . $this->autoid();
        }

        return null;
    }

    /**
     * @return File|Page|Site|null
     */
    public function self()
    {
        if ($this->isPage() || $this->isStructureObject()) {
            return $this->page();
        }
        if ($this->isFile()) {
            return $this->file();
        }
        return null;
    }

    public function get()
    {
        return $this->toObject();
    }

    public function __debugInfo(): array
    {
        return $this->data->__debugInfo();
    }

    public function __get($name)
    {
        return $this->data->$name;
    }
}
