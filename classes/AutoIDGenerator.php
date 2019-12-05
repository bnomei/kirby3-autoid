<?php

declare(strict_types=1);

namespace Bnomei;

interface AutoIDGenerator
{
    public function __construct($seed);

    public function generate(): string;
}
