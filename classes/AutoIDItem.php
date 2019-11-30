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
        return $this->data->modified;
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

    public function isPage(): bool
    {
        return $this->data->kind === self::KIND_PAGE;
    }

    public function isFile(): bool
    {
        return $this->data->kind === self::KIND_FILE;
    }

    public function toObject()
    {
        if ($this->isPage()) {
            return $this->page();
        } elseif ($this->isFile()) {
            return $this->file();
        }
        return null;
    }

    public function id()
    {
        return $this->page . ($this->filename ? '/' . $this->filename : '');
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
