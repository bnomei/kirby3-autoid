<?php

Kirby::plugin('bnomei/autoid', [
    'options' => [
        'cache' => true,
        'generator' => function (string $seed = null) {
            // override with custom callback if needed
            return \Bnomei\AutoID::defaultGenerator();
        },
        'generator.break' => 42, // generator loops for uniqueness
        'index.pages' => true,
        'index.structures' => true,
        'index.files' => true,
        'index' => function ():\Kirby\Cms\Pages {
            return kirby()->site()->pages()->index();
        },
        'log.enabled' => false,
        'log' => function (string $msg, string $level = 'info', array $context = []):bool {
            if (option('bnomei.autoid.log.enabled') && function_exists('kirbyLog')) {
                kirbyLog('bnomei.autoid.log')->log($msg, $level, $context);
                return true;
            }
            return false;
        },
        'modified' => [
            'recursive' => false,
            'expire' => 30, // seconds
        ],
    ],
    'pagesMethods' => [ // PAGES not PAGE
        'autoid' => function ($autoid) {
            return \Bnomei\AutoID::find($autoid);
        },
    ],
    'fieldMethods' => [
        'fromAutoID' => function ($field) {
            return \Bnomei\AutoID::find((string)$field->value);
        },
    ],
    'fields' => [
        'autoid' => [
          'props' => [
            'autoid' => function () {
                $fieldname = \Bnomei\AutoID::fieldname();
                return $this->$fieldname();
            }
          ]
        ]
    ],
    'hooks' => [
        'page.create:after' => function ($page) {
            if (!(option('bnomei.autoid.index.pages') && option('bnomei.autoid.index.structures'))) {
                return;
            }
            \Bnomei\AutoID::addPage($page);
        },
        'page.update:after' => function ($newPage, $oldPage) {
            if (!(option('bnomei.autoid.index.pages') && option('bnomei.autoid.index.structures'))) {
                return;
            }
            \Bnomei\AutoID::addPage($newPage);
        },
        'page.changeSlug:after' => function ($newPage, $oldPage) {
            if (!(option('bnomei.autoid.index.pages') && option('bnomei.autoid.index.structures'))) {
                return;
            }
            \Bnomei\AutoID::addPage($newPage);
        },
        'page.delete:before' => function ($page) {
            if (!(option('bnomei.autoid.index.pages') && option('bnomei.autoid.index.structures'))) {
                return;
            }
            \Bnomei\AutoID::removePage($page);
        },
        'file.create:after' => function ($file) {
            if (!option('bnomei.autoid.index.files')) {
                return;
            }
            \Bnomei\AutoID::addFile($file);
        },
        'file.update:after' => function ($newFile, $oldFile) {
            if (!option('bnomei.autoid.index.files')) {
                return;
            }
            // update filename in index
            \Bnomei\AutoID::removeFile($oldFile);
            \Bnomei\AutoID::addFile($newFile);
        },
        'file.changeName:after' => function ($newFile, $oldFile) {
            if (!option('bnomei.autoid.index.files')) {
                return;
            }
            // TODO: will trigger update anyway?
            \Bnomei\AutoID::removeFile($oldFile);
            \Bnomei\AutoID::addFile($newFile);
        },
        'file.delete:before' => function ($file) {
            if (!option('bnomei.autoid.index.files')) {
                return;
            }
            \Bnomei\AutoID::removeFile($file);
        },
    ]
]);

if (!class_exists('Bnomei\AutoID')) {
    require_once __DIR__ . '/classes/autoid.php';
}

if (!function_exists('autoid')) {
    function autoid($obj = null)
    {
        if (is_string($obj)) {
            return \Bnomei\AutoID::find($obj);
        } elseif (is_a($obj, 'Kirby\Cms\Field')) {
            return \Bnomei\AutoID::find($obj->value());
        } elseif (is_a($obj, 'Kirby\Cms\Page')) {
            \Bnomei\AutoID::addPage($obj);
            return \Bnomei\AutoID::find($obj->${\Bnomei\AutoID::fieldname()});
        } elseif (is_a($obj, 'Kirby\Cms\StructureObject')) {
            if ($page = $obj->page()) {
                \Bnomei\AutoID::addPage($page);
                return \Bnomei\AutoID::find($obj->${\Bnomei\AutoID::fieldname()});
            }
        } elseif (is_a($obj, 'Kirby\Cms\File')) {
            \Bnomei\AutoID::addFile($obj);
            return \Bnomei\AutoID::find($obj->${\Bnomei\AutoID::fieldname()});
        } else {
            return \Bnomei\AutoID::collection();
        }
    }
}

if (!class_exists('Bnomei\Modified')) {
    require_once __DIR__ . '/classes/modified.php';
}

if (!function_exists('modified')) {
    function modified(string $group, $objects = null, $options = null)
    {
        $result = \Bnomei\Modified::findGroup($group);

        if (!$objects && !$result) {
            return null;
        } elseif (!$objects && $result) {
            return $result;
        } else {
            return \Bnomei\Modified::registerGroup($group, $objects, $options);
        }
    }
}

if (!function_exists('modifiedHash')) {
    function modifiedHash(string $group)
    {
        return \Bnomei\Modified::modifiedGroup($group);
    }
}
