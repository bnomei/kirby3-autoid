<?php

declare(strict_types=1);

namespace Bnomei;

use Kirby\Cms\Page;

final class AutoIDIndexer
{
    /**
     * @var \Kirby\Cms\Site
     */
    private $root;

    public function __construct(?Page $root = null)
    {
        if (! $root) {
            $root = kirby()->site();
        }
        $this->root = $root;
    }

    public function next(): \Generator
    {
        $next = $this->root;
        while($next) {
            yield $next;
            foreach($next->children() as $child) {
                $recursive = new self($child);
                foreach ($recursive->next() as $item) {
                    yield $item;
                }
            }
            $next = null;
        }
    }
}
