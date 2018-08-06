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
        // could do 'root' option instead of kirby()->site()
        // could do 'filter.pages' and 'filter.files' callback (==> $objcollection->filter($callback))
    ],
    'pagesMethods' => [ // PAGES not PAGE
        'autoid' => function ($autoid) {
            return \Bnomei\AutoID::find($autoid);
        },
    ],
    'fields' => [
        'autoid' => [
          'props' => [
            'autoid' => function () {
              return $this->${\Bnomei\AutoID::fieldname()}();
            }
          ]
        ]
    ],
    'hooks' => [
        'page.create:after' => function ($page) {
            if (!(option('bnomei.autoid.index.pages') && option('bnomei.autoid.index.structures'))) return;
            \Bnomei\AutoID::addPage($page);
        },
        'page.update:after' => function ($newPage, $oldPage) {
            if (!(option('bnomei.autoid.index.pages') && option('bnomei.autoid.index.structures'))) return;
            \Bnomei\AutoID::addPage($page);
        },
        'page.delete:before' => function ($page) {
            if (!(option('bnomei.autoid.index.pages') && option('bnomei.autoid.index.structures'))) return;
            \Bnomei\AutoID::removePage($page);
        },
        'file.create:after' => function ($file) {
            if (!option('bnomei.autoid.index.files')) return;
            \Bnomei\AutoID::addFile($file);
        },
        'file.update:after' => function ($newFile, $oldFile) {
            if (!option('bnomei.autoid.index.files')) return;
            // update filename in index
            \Bnomei\AutoID::removeFile($file);
            \Bnomei\AutoID::addFile($file);
        },
        'file.changeName:after' => function ($newFile, $oldFile) {
            if (!option('bnomei.autoid.index.files')) return;
            // TODO: will trigger update anyway?
            \Bnomei\AutoID::removeFile($file);
            \Bnomei\AutoID::addFile($file);
        },
        'file.delete:before' => function ($file) {
            if (!option('bnomei.autoid.index.files')) return;
            \Bnomei\AutoID::removeFile($file);
        },
    ]
]);

if(!function_exists('autoid')) {
    function autoid($autoid) {
        return \Bnomei\AutoID::find($autoid);
    }
}
