<?php

@include_once __DIR__ . '/vendor/autoload.php';

if (! class_exists('Bnomei\AutoID')) {
    require_once __DIR__ . '/classes/AutoID.php';
}

if (! function_exists('autoid')) {
    function autoid($obj = \Bnomei\AutoID::GENERATE)
    {
        \Bnomei\AutoID::index();

        if ($obj === \Bnomei\AutoID::GENERATE) {
            return \Bnomei\AutoID::generate();
        }
        if (is_string($obj) ||
            is_a($obj, 'Kirby\Cms\Field')) {
            return \Bnomei\AutoID::find($obj);
        }
        if (is_a($obj, 'Kirby\Cms\Page') ||
            is_a($obj, 'Kirby\Cms\File')) {
            $find = null;
            if ($obj->{\Bnomei\AutoID::FIELDNAME}()->isNotEmpty()) {
                $find = \Bnomei\AutoID::find(
                    $obj->{\Bnomei\AutoID::FIELDNAME}()
                );
            }
            if (! $find) {
                \Bnomei\AutoID::push($obj);
                $find = \Bnomei\AutoID::findByID($obj->id());
            }
            return $find;
        }
        return null;
    }

    function modified($autoid): ?int
    {
        \Bnomei\AutoID::index();
        return \Bnomei\AutoID::modified($autoid);
    }

    function searchForTemplate(string $template, string $rootId = ''): \Kirby\Cms\Collection
    {
        return \Bnomei\AutoIDDatabase::singleton()->findByTemplate($template, $rootId);
    }
}

Kirby::plugin('bnomei/autoid', [
    'options' => [
        'cache' => true,
        'generator' => function (?string $seed = null) {
            // override with custom callback if needed
            return (new \Bnomei\TokenGenerator($seed))->generate();
            // return (new \Bnomei\IncrementingGenerator(0))->generate();
            // return (new \Bnomei\NanoGenerator())->generate();
            // return (new \Bnomei\UUIDGenerator(site()->url()))->generate();
        },
        'generator.break' => 42, // generator loops for uniqueness
        'tinyurl.url' => function () {
            return kirby()->url('index');
        },
        'tinyurl.folder' => 'x',
    ],
    'fileMethods' => [ // FILE
        'AUTOID' => function () { // casesensitive
            $db = \Bnomei\AutoIDDatabase::singleton();
            if (! $db->exists($this->autoid())) {
                \Bnomei\AutoID::push($this);
                return $db->findByID($this->id())->autoid();
            }
            return $this->autoid()->value();
        },
    ],
    'pageMethods' => [ // PAGE
        'AUTOID' => function () { // casesensitive
            $db = \Bnomei\AutoIDDatabase::singleton();
            if (! $db->exists($this->autoid())) {
                \Bnomei\AutoID::push($this);
                return $db->findByID($this->id())->autoid();
            }
            return $this->autoid()->value();
        },
        'tinyurl' => function (): string {
            $url = \Bnomei\AutoID::tinyurl(
                $this->{\Bnomei\AutoID::FIELDNAME}()
            );
            if ($url) {
                return $url;
            }
            return site()->errorPage()->url();
        },
        'tinyUrl' => function (): string {
            $url = \Bnomei\AutoID::tinyurl(
                $this->{\Bnomei\AutoID::FIELDNAME}()
            );
            if ($url) {
                return $url;
            }
            return site()->errorPage()->url();
        },
        'searchForTemplate' => function (string $template): \Kirby\Cms\Collection {
            return searchForTemplate($template, $this->id());
        },
    ],
    'pagesMethods' => [ // PAGES not PAGE
        'autoid' => function ($autoid) {
            return autoid($autoid);
        },
    ],
    'siteMethods' => [
        'searchForTemplate' => function (string $template): \Kirby\Cms\Collection {
            return searchForTemplate($template, '/');
        },
    ],
    'fieldMethods' => [
        'fromAutoID' => function ($field) {
            return autoid($field->value);
        },
    ],
    'fields' => [
        'autoid' => [
            'props' => [
                'value' => function (string $value = null) {
                    return $value;
                },
            ],
        ],
    ],
    'routes' => function ($kirby) {
        $folder = $kirby->option('bnomei.autoid.tinyurl.folder');
        return [
            [
                'pattern' => $folder . '/(:any)',
                'method' => 'GET',
                'action' => function ($autoid) {
                    $page = autoid($autoid);
                    if ($page) {
                        return \go($page->url(), 302);
                    }
                    return \go(site()->errorPage()->url(), 404);
                },
            ],
        ];
    },
    'hooks' => [
        'page.create:after' => function ($page) {
            \Bnomei\AutoID::push($page);
        },
        'page.update:after' => function ($newPage, $oldPage) {
            \Bnomei\AutoID::remove($oldPage);
            \Bnomei\AutoID::push($newPage);
        },
        'page.duplicate:after' => function ($newPage) {
            \Bnomei\AutoID::push($newPage, true);
        },
        'page.changeNum:after' => function ($newPage, $oldPage) {
            \Bnomei\AutoID::remove($oldPage);
            \Bnomei\AutoID::push($newPage);
        },
        'page.changeSlug:after' => function ($newPage, $oldPage) {
            \Bnomei\AutoID::remove($oldPage);
            \Bnomei\AutoID::push($newPage);
        },
        'page.changeStatus:after' => function ($newPage, $oldPage) {
            \Bnomei\AutoID::remove($oldPage);
            \Bnomei\AutoID::push($newPage);
        },
        'page.changeTemplate:after' => function ($newPage, $oldPage) {
            \Bnomei\AutoID::remove($oldPage);
            \Bnomei\AutoID::push($newPage);
        },
        'page.changeTitle:after' => function ($newPage, $oldPage) {
            \Bnomei\AutoID::remove($oldPage);
            \Bnomei\AutoID::push($newPage);
        },
        'page.delete:before' => function ($page) {
            \Bnomei\AutoID::remove($page);
        },
        'file.create:after' => function ($file) {
            \Bnomei\AutoID::push($file);
        },
        'file.update:after' => function ($newFile, $oldFile) {
            \Bnomei\AutoID::remove($oldFile);
            \Bnomei\AutoID::push($newFile);
        },
        'file.replace:after' => function ($newFile, $oldFile) {
            \Bnomei\AutoID::remove($oldFile);
            \Bnomei\AutoID::push($newFile);
        },
        'file.changeName:after' => function ($newFile, $oldFile) {
            \Bnomei\AutoID::remove($oldFile);
            \Bnomei\AutoID::push($newFile);
        },
        'file.changeSort:after' => function ($newFile, $oldFile) {
            \Bnomei\AutoID::remove($oldFile);
            \Bnomei\AutoID::push($newFile);
        },
        'file.delete:before' => function ($file) {
            \Bnomei\AutoID::remove($file);
        },
        'site.update:after' => function ($newSite, $oldSite) {
            \Bnomei\AutoID::remove($oldSite);
            \Bnomei\AutoID::push($newSite);
        },
    ],
]);
