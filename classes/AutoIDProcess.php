<?php

declare(strict_types=1);

namespace Bnomei;

use Exception;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Cms\Site;
use Kirby\Data\Yaml;
use Kirby\Toolkit\A;

final class AutoIDProcess
{
    private $object;
    private $overwrite;
    private $hasChanges;
    private $update;
    private $autoids;
    private $indexed;

    public function __construct($object, bool $overwrite = false)
    {
        if (is_a($object, Page::class)) {
            foreach ($object->files() as $file) {
                new self($file, $overwrite);
            }
        }

        $this->object = $object;
        $this->overwrite = $overwrite;
        $this->hasChanges = false;
        $this->indexed = false;
        $this->update = [];
        $this->autoids = [];

        $this->indexPageOrFile();
        $this->indexStructures();
        $this->update();
    }

    public function isIndexed(): bool
    {
        return $this->indexed;
    }

    private function indexPageOrFile(): void
    {
        $autoid = null;

        if ($this->object->{AutoID::FIELDNAME}()->isNotEmpty()) {
            $autoid = $this->object->{AutoID::FIELDNAME}()->value();
        }

        if ($this->overwrite || $this->object->{AutoID::FIELDNAME}()->isEmpty()) {
            $autoid = AutoID::generate();
            if (! $autoid) {
                return;
            }
            if ($this->object->blueprint()->field(AutoID::FIELDNAME)) {
                $this->update[AutoID::FIELDNAME] = $autoid;
                $this->autoids[] = $autoid;
                $this->hasChanges = true;
            } else {
                $autoid = null;
            }
        }

        if ($autoid) {
            AutoIDDatabase::singleton()->insertOrUpdate(
                $this->createItem($autoid)
            );
        }
    }

    private function indexStructures(): void
    {
        $data = [];
        foreach ($this->object->blueprint()->fields() as $field) {
            if (A::get($field, 'type') !== 'structure') {
                continue;
            }
            $fieldname = A::get($field, 'name');
            if (! $fieldname) {
                continue;
            }
            $field = $this->object->{$fieldname}();
            if ($field->isEmpty()) {
                continue;
            }
            try {
                $yaml = Yaml::decode($field->value());
                $yaml = $this->indexArray($yaml, [$fieldname]);
                $data[$fieldname] = Yaml::encode($yaml);
            } catch (Exception $exception) {
            }
        }
        $this->update = array_merge($this->update, $data);
    }

    private function indexArray(array $data, array $tree = []): array
    {
        $copy = $data;
        foreach ($data as $key => $value) {
            if ($key === AutoID::FIELDNAME) {
                $autoid = $value;
                if ($this->overwrite || ! $value || $value === 'null') {
                    $autoid = AutoID::generate();
                    if ($autoid) {
                        $copy[$key] = $autoid;
                        $this->autoids[] = $autoid;
                        $this->hasChanges = true;
                    }
                }

                AutoIDDatabase::singleton()->insertOrUpdate(
                    $this->createItem($autoid, $tree)
                );
            } elseif (is_array($value)) {
                $next = $tree; // copy
                $next[] = $key;
                $copy[$key] = $this->indexArray($value, $next);
            }
        }
        return $copy;
    }

    private function update(): void
    {
        if (! $this->hasChanges) {
            $this->indexed = true;
            return;
        }

        try {
            kirby()->impersonate('kirby');
            $this->object->update($this->update);
        } catch (Exception $exception) {
            $this->revert();
        } finally {
            $this->indexed = true;
        }
    }

    private function revert(): void
    {
        foreach ($this->autoids as $autoid) {
            AutoIDDatabase::singleton()->delete($autoid);
        }
        $this->autoids = [];
    }

    private function createItem(string $autoid, array $tree = null): AutoIDItem
    {
        if (is_array($tree) && (
                is_a($this->object, Page::class) ||
                is_a($this->object, File::class) ||
                is_a($this->object, Site::class)
            )
        ) {
            $data = $this->itemFromStructureObject($this->object, $tree);
        } elseif (is_a($this->object, Page::class)) {
            $data = $this->itemFromPage($this->object);
        } elseif (is_a($this->object, File::class)) {
            $data = $this->itemFromFile($this->object);
        }

        return new AutoIDItem(array_merge($data, [
            'autoid' => $autoid,
        ]));
    }

    private function itemFromPage($object): array
    {
        return [
            'page' => $object->id(),
            'modified' => $object->modified(),
            'kind' => AutoIDItem::KIND_PAGE,
        ];
    }

    private function itemFromFile($object): array
    {
        return [
            'page' => $object->page()->id(),
            'filename' => $object->filename(),
            'modified' => $object->modified(),
            'kind' => AutoIDItem::KIND_FILE,
        ];
    }

    private function itemFromStructureObject($object, array $tree): array
    {
        return [
            'page' => is_a($this->object, Site::class) ? '$' : $object->id(),
            'modified' => $object->modified(),
            'structure' => implode(',', $tree),
            'kind' => AutoIDItem::KIND_STRUCTUREOBJECT,
        ];
    }
}
