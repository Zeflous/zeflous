<?php

declare(strict_types=1);

namespace Zef\Test\Container\Autowiring;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Autowiring\Inject;

/**
 * @internal
 */
#[CoversClass(Inject::class)]
final class InjectTest extends TestCase
{
    public function testCarriesTheDeclaredIdentifier(): void
    {
        self::assertSame('app.clock', new Inject('app.clock')->id);
    }

    public function testAnEmptyIdentifierIsRefused(): void
    {
        try {
            new Inject('');
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                '#[Inject] requires a non-empty service identifier.',
                $autowiringException->getMessage(),
            );
        }
    }

    public function testAWhitespaceIdentifierIsRefused(): void
    {
        try {
            new Inject('   ');
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                '#[Inject] requires a non-empty service identifier.',
                $autowiringException->getMessage(),
            );
        }
    }
}
