<?php

Kirby::plugin('bnomei/autoid', [
    'options' => [
        'cache' => true,
        'generator' => function (string $seed = null) {
            return \Bnomei\AutoID::defaultGenerator($seed);
        },
    ],
    'pagesMethods' => [ // PAGES not PAGE
        'autoid' => function ($autoid) {
            // TODO: return page object or structure-field (has ref to parent page) from lookup table
            return null;
        },
    ],
    'hooks' => [
        'page.create:after' => function ($page) {
            \Bnomei\AutoID::addPage($page);
        },
        'page.update:after' => function ($newPage, $oldPage) {
            // TODO: action needed?
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
            // TODO: will trigger update?
        },
        'file.delete:before' => function ($file) {
            \Bnomei\AutoID::removeFile($file);
        },
    ]
]);
