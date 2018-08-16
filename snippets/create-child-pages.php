<?php

$img = $page->images()->first();
$c = $page->children()->count() + $page->drafts()->count();

while($c < 10) {
    $child = bin2hex(random_bytes(10));

    kirby()->impersonate('kirby');
    $pchi = $page->createChild([
        'slug' => $child, 
        'template' => 'autoidtest',
        'content' => [
        'title' => $child,
        'text' => md5($child),
        'anystructure' => [
            ['text' => md5($child.'1')],
            ['text' => md5($child.'2')],
        ]
        ]
    ]);

    kirby()->impersonate('kirby');
    $pchi->createFile([
        'source' => $img->root(),
        'content' => [
        'template' => 'autoidimage',
        ]
    ]);
    $c++;
}

foreach($page->drafts() as $pchi) {
    kirby()->impersonate('kirby');
    $pchi->changeStatus('listed');
}
