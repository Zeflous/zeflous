<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\NamedArguments;

/**
 * @internal
 */
#[CoversClass(NamedArguments::class)]
final class NamedArgumentsTest extends TestCase
{
    public function testAMapOfValuesAndReferencesAnswersVerbatim(): void
    {
        self::assertSame(
            ['salutation' => 'Hello', 'verbosity' => ['service' => 'app.count']],
            NamedArguments::assert('app.x', [
                'salutation' => 'Hello',
                'verbosity' => ['service' => 'app.count'],
            ]),
        );
    }

    public function testAnEmptyMapAnswersTheEmptyDeclaration(): void
    {
        self::assertSame([], NamedArguments::assert('app.x', []));
    }

    public function testANonMapIsRefusedWithTypeAndIdentifier(): void
    {
        try {
            NamedArguments::assert('app.x', 'nope');
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "Service 'app.x' arguments must be a map, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnEmptyNameIsRefused(): void
    {
        try {
            NamedArguments::assert('app.x', ['' => 'value']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "Service 'app.x' name-keyed arguments must carry non-empty parameter names, string given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAnIntegerNameIsRefused(): void
    {
        try {
            NamedArguments::assert('app.x', ['salutation' => 'Hello', 1 => 'stray']);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "Service 'app.x' name-keyed arguments must carry non-empty parameter names, int given.",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAMultiKeyReferenceIsRefused(): void
    {
        try {
            NamedArguments::assert('app.x', ['verbosity' => ['service' => 'app.count', 'extra' => 1]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "Service 'app.x' argument reference must be the single key 'service'",
                $definitionException->getMessage(),
            );
        }
    }

    public function testAReferenceWithoutAStringIdentifierIsRefused(): void
    {
        try {
            NamedArguments::assert('app.x', ['verbosity' => ['service' => 42]]);
            self::fail('Expected a DefinitionException to be thrown.');
        } catch (DefinitionException $definitionException) {
            self::assertStringContainsString(
                "Service 'app.x' argument reference must name a non-empty service identifier, int given.",
                $definitionException->getMessage(),
            );
        }
    }
}
