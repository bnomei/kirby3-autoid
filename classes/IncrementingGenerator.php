<?php

declare(strict_types=1);

namespace Bnomei;

use Exception;
use Kirby\Toolkit\F;

final class IncrementingGenerator implements AutoIDGenerator
{
    private $seed;

    public function __construct($seed = 0)
    {
        $this->seed = abs(intval($seed));
    }

    public function generate(): string
    {
        $id = $this->seed;
        $file = self::file();
        if (! F::exists($file)) {
            F::write($file, $id);
        } else {
            $id = intval(F::read($file));
        }
        $id += 1;
        // @codeCoverageIgnoreStart
        if (F::write($file, $id) === false) {
            throw new Exception('The file "' . $file . '" is not writable');
        }
        // @codeCoverageIgnoreEnd

        return strval($id);
    }

    private static $file;

    public static function file(?string $path = null): string
    {
        if (self::$file) {
            return self::$file;
        }
        if ($path === null) {
            $path = kirby()->roots()->content() . '/.autoid'; // NOT .txt !!
        }
        if (! self::$file && $path) {
            self::$file = $path;
        }
        return self::$file;
    }
}
