<?php

declare(strict_types=1);

namespace Bnomei;

final class TokenGenerator implements AutoIDGenerator
{
    public function __construct($seed = null)
    {
        $this->seed = (string) $seed;
    }

    public function generate(int $length = 8, bool $withLower = true, bool $withUpper = false, bool $withNumbers = true): string
    {
        // alphanumeric: 8 chars, lowercase and numbers
        // (26 + 10) ^ 8 = 2.821.109.907.456 = ~ 2.8 trillion possibilities

        $codeAlphabet = $this->seed;
        if ($withUpper) {
            $codeAlphabet .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        if ($withLower) {
            $codeAlphabet .= 'abcdefghijklmnopqrstuvwxyz';
        }
        if ($withNumbers) {
            $codeAlphabet .= '0123456789';
        }
        $max = strlen($codeAlphabet);

        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $codeAlphabet[random_int(0, $max - 1)];
        }

        return $token;
    }
}
