<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cms\File;
use Kirby\Cms\Page;
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

    public function page(): ?Page
    {
        return page($this->data->page);
    }

    public function file(): ?File
    {
        $page = $this->page();
        if ($page) {
            return $page->file($this->data->filename);
        }
        return null;
    }

    public function structureObject(): array
    {
        $tree = array_map(static function ($value) {
            return is_numeric($value) ? intval($value) : $value;
        }, explode(',', $this->structure));

        return $tree;

        // TODO: return recursive Structure Field or value?
//        $tree = explode(',', $this->structure);
//        $root = array_shift($tree);
//        $field = $this->page()->{$root}();
//
//        foreach ($tree as $leaf) {
//            if ($field->isNotEmpty()) {
//                foreach ($field->toStructure() as $obj) {
//                    if ($obj->{$leaf}()->isNotEmpty()) {
//                        $field = $obj;
//                    } else {
//                        return null;
//                    }
//                }
//            } else {
//                return null;
//            }
//        }
//
//        return $field;
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
            return $this->page;
        }
        if ($this->isFile()) {
            return $this->page . '/' . $this->filename;
        }
        if ($this->isStructureObject()) {
            // tree is not unique post update since its sortable. use autoid as id
            return $this->page . '#' . $this->autoid();
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
