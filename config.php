<?php

Kirby::plugin('bnomei/autoid', [
    'options' => [
        'cache' => true,
        'generator' => function (string $seed = null) {
            // override with custom callback if needed
            return \Bnomei\AutoID::defaultGenerator();
        },
        'break' => 42, // generator loops for uniqueness
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
            \Bnomei\AutoID::addPage($page);
        },
        'page.update:after' => function ($newPage, $oldPage) {
            \Bnomei\AutoID::addPage($page);
        },
        'page.delete:before' => function ($page) {
            \Bnomei\AutoID::removePage($page);
        },
        'file.create:after' => function ($file) {
            \Bnomei\AutoID::addFile($file);
        },
        'file.update:after' => function ($newFile, $oldFile) {
            // update filename in index
            \Bnomei\AutoID::removeFile($file);
            \Bnomei\AutoID::addFile($file);
        },
        'file.changeName:after' => function ($newFile, $oldFile) {
            // TODO: will trigger update anyway?
            \Bnomei\AutoID::removeFile($file);
            \Bnomei\AutoID::addFile($file);
        },
        'file.delete:before' => function ($file) {
            \Bnomei\AutoID::removeFile($file);
        },
    ]
]);

if(!function_exists('autoid')) {
    function autoid($autoid) {
        return \Bnomei\AutoID::find($autoid);
    }
}
