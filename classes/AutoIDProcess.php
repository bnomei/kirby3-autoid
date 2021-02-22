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

        if (! $this->overwrite) {
            // skip without reading the autoid from content file
            $diruri = null;
            if(is_a($object, Page::class)) {
                $diruri = $this->object->diruri();
            } elseif(is_a($object, File::class)) {
                $diruri = $this->object->parent()->diruri() . '@' . $this->object->filename();
            } elseif(is_a($object, Site::class)) {
                $diruri = '$';
            }

            $this->indexed = AutoIDDatabase::singleton()->findByDiruri($diruri) !== null;
        }

        if (! $this->indexed) {
            $this->indexPageOrFile();
            $this->indexStructures();
            $this->update();
        }
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
            try {
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
            // use save() not update() to avoid hooks
            $this->object->save($this->update);
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
        } elseif (is_a($this->object, Site::class)) {
            $data = $this->itemFromSite($this->object);
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
            'diruri' => $object->diruri(),
            'modified' => $object->modified(),
            'kind' => AutoIDItem::KIND_PAGE,
            'template' => (string) $object->intendedTemplate(),
            'draft' => $object->isDraft() ? 1 : 0,
        ];
    }

    private function itemFromSite($object): array
    {
        return [
            'page' => '$',
            'diruri' => '$',
            'modified' => filemtime($object->contentFile()), // just site not ALL
            'kind' => AutoIDItem::KIND_PAGE,
            'template' => 'site',
            'draft' => null,
        ];
    }

    private function itemFromFile($object): array
    {
        return [
            'page' => $object->parent()->id(),
            'diruri' => $object->parent()->diruri() . '@' . $object->filename(),
            'filename' => $object->filename(),
            'modified' => $object->modified(),
            'kind' => AutoIDItem::KIND_FILE,
            'template' => (string) $object->template(),
            'draft' => null,
        ];
    }

    private function itemFromStructureObject($object, array $tree): array
    {
        $id = is_a($this->object, Site::class) ? '$' : $object->id();
        $diruri = is_a($this->object, Site::class) ? '$' : $object->diruri();
        $treeFlat = implode(',', $tree);
        $modified = is_a($this->object, Site::class) ?
            filemtime($object->contentFile()) :
            $object->modified();
        return [
            'page' => $id,
            'diruri' => $diruri . '#' . $treeFlat,
            'modified' => $modified,
            'structure' => $treeFlat,
            'kind' => AutoIDItem::KIND_STRUCTUREOBJECT,
            'template' => 'structureobject',
            'draft' => null,
        ];
    }
}
