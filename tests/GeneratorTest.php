<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Bnomei\IncrementingGenerator;
use Bnomei\NanoGenerator;
use Bnomei\UUIDGenerator;
use Bnomei\TokenGenerator;
use Kirby\Toolkit\F;
use PHPUnit\Framework\TestCase;

final class GeneratorTest extends TestCase
{
    public function testIncrementing()
    {
        IncrementingGenerator::file(
            kirby()->roots()->content() . '/.autoid'
        );
        $gen = new IncrementingGenerator();

        $this->assertFalse(
            F::exists(IncrementingGenerator::file())
        );

        $this->assertEquals(1, $gen->generate());

        $this->assertTrue(
            F::exists(IncrementingGenerator::file())
        );

        $this->assertEquals(2, $gen->generate());
        $this->assertEquals(3, $gen->generate());

        F::remove(IncrementingGenerator::file());
        $this->assertFalse(
            F::exists(IncrementingGenerator::file())
        );
    }

    public function testUUID()
    {
        $gen = new UUIDGenerator();

        $this->assertTrue(strlen($gen->generate()) === 36);
        $this->assertTrue(strlen($gen->generate(4)) === 36);
        $this->assertTrue(strlen($gen->generate(3)) === 36);
        $this->assertTrue(strlen($gen->generate(1)) === 36);
    }

    public function testNano()
    {
        $gen = new NanoGenerator();

        $this->assertTrue(strlen($gen->generate()) === 21);
        $this->assertTrue(strlen($gen->generate(4)) === 4);
    }

    public function testToken()
    {
        $gen = new TokenGenerator();

        $this->assertRegExp(
            '/^[a-z0-9]{8}$/',
            $gen->generate()
        );

        $this->assertRegExp(
            '/^[a-zA-Z0-9]{16}$/',
            $gen->generate(16, true, true, true)
        );
    }
}
