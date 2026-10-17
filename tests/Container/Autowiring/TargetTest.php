<?php

declare(strict_types=1);

namespace Zef\Test\Container\Autowiring;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Autowiring\Target;
use Zef\Test\Support\Container\Autowiring\FileLogger;
use Zef\Test\Support\Container\Autowiring\LoggerInterface;

/**
 * @internal
 */
#[CoversClass(Target::class)]
final class TargetTest extends TestCase
{
    public function testCarriesTheDeclaredClass(): void
    {
        self::assertSame(FileLogger::class, new Target(FileLogger::class)->class);
    }

    public function testAnInterfaceIsAcceptedAsAReference(): void
    {
        self::assertSame(LoggerInterface::class, new Target(LoggerInterface::class)->class);
    }

    public function testAnUnknownClassIsRefused(): void
    {
        try {
            new Target('No\Such\Class');
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "#[Target] references the unknown class, interface or enum 'No\\Such\\Class'.",
                $autowiringException->getMessage(),
            );
        }
    }
}
