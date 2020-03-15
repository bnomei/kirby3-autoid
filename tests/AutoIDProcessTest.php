<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Bnomei\AutoID;
use Bnomei\AutoIDProcess;
use Kirby\Cms\File;
use Kirby\Cms\Page;
use Kirby\Toolkit\Str;
use PHPUnit\Framework\TestCase;

class AutoIDProcessTest extends TestCase
{
    public function randomPage(): ?Page
    {
        return site()->pages()->index()->notTemplate('home')->shuffle()->first();
    }

    public function randomFile(): ?File
    {
        return site()->pages()->index()->notTemplate('home')->files()->shuffle()->first();
    }

    public function testProcessPage()
    {
        $page = $this->randomPage();
        $process = new AutoIDProcess($page);

        $this->assertTrue($process->isIndexed());
    }

    public function testProcessFileOverwrite()
    {
        $file = $this->randomFile();

        // NOTE: this will change ONE file per test run.
        // will show up in git and can be reverted.
        $process = new AutoIDProcess($file, true);
        $this->assertTrue($process->isIndexed());
    }
}
