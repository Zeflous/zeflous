<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Version;

#[CoversClass(Version::class)]
final class VersionTest extends TestCase
{
    public function testCurrentReturnsTheDeclaredVersion(): void
    {
        self::assertSame(Version::VERSION, Version::current());
    }

    public function testVersionStringIsNotEmpty(): void
    {
        self::assertNotSame('', Version::current());
    }
}
