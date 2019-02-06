<?php

/*
  NOTE: since creating and changing status programatically soon after still has issues refresh page until
        there are no more pages created.
 */

if (!function_exists('create_child_pages_recursive')) {
    function create_child_pages_recursive(\Kirby\Cms\Page $page, int $depth, int $childs)
    {
        $c = $page->children()->count();

        foreach ($page->drafts() as $pchi) {
            $c++;
            kirby()->impersonate('kirby');
            $pchi->changeStatus('listed', $c);
            // sleep(1);
        }
    
        $img = $page->images()->first();

        while ($c < $childs) {
            $title = bin2hex(random_bytes(10));

            kirby()->impersonate('kirby');
            $pchi = $page->createChild([
            'slug' => 'test-'.\Kirby\Toolkit\Str::slug($title),
            'template' => 'autoidtest',
            'content' => [
            'title' => $title,
            'text' => md5($title),
            'anystructure' => [
                ['text' => md5($title.'1')],
                ['text' => md5($title.'2')],
            ]
            ]
        ]);
            // sleep(1);

            if ($img) {
                kirby()->impersonate('kirby');
                $pchi->createFile([
                'source' => $img->root(),
                'content' => [
                'template' => 'autoidimage',
                ]
            ]);
                // sleep(1);
            }
            $c++;
        }

        $depth--;
        foreach ($page->children()->visible() as $pchi) {
            if ($depth > 0) {
                create_child_pages_recursive($pchi, $depth, $childs);
            }
        }
    }
}

$depth = isset($depth) ? $depth : 3;
$childs = isset($childs) ? $childs : 3;
create_child_pages_recursive($page, $depth, $childs);
