<?php

declare(strict_types=1);

namespace Bnomei;

use Hidehalo\Nanoid\Client;

final class NanoGenerator implements AutoIDGenerator
{
    public function __construct($seed = null)
    {
        $this->seed = (string)$seed;
    }

    public function generate(int $length = 21, $mode = Client::MODE_NORMAL): string
    {
        $client = new Client();
        if (is_string($this->seed)) {
            return ($client)->formattedId($this->seed, $length);
        }
        return ($client)->generateId($length, $mode);
    }
}
