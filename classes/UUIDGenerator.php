<?php

declare(strict_types=1);

namespace Bnomei;

use Ramsey\Uuid\Uuid;

final class UUIDGenerator implements AutoIDGenerator
{
    private $name;

    public function __construct($name = null)
    {
        $this->name = (string) $name;
    }

    public function generate(int $version = 5): string
    {
        if ($version >= 5) {
            return Uuid::uuid5(Uuid::NAMESPACE_DNS, $this->name)->toString();
        }
        if ($version === 4) {
            return Uuid::uuid4()->toString();
        }
        if ($version === 3) {
            return Uuid::uuid3(Uuid::NAMESPACE_DNS, $this->name)->toString();
        }

        return Uuid::uuid1()->toString();
    }
}
