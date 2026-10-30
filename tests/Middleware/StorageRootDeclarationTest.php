<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Middleware\StorageRootDeclaration;

/**
 * Pins the storage map's declaration fold: the root the host
 * declared, the null every silent spelling answers (no block, an
 * empty map, the empty string), and the strict census -- a map that
 * is not a map, an unknown knob, a wrong-typed knob name, a
 * wrong-typed root -- each refused with its own name and the known
 * list spelled, because a silent typo must never fall through to the
 * ad-hoc posture unnoticed.
 *
 * @internal
 */
#[CoversClass(StorageRootDeclaration::class)]
final class StorageRootDeclarationTest extends TestCase
{
    public function testAnAbsentStorageMapAnswersNull(): void
    {
        self::assertNull(
            StorageRootDeclaration::root(new Config([])),
            'no storage block declares no layout',
        );
    }

    public function testAnEmptyStorageMapAnswersNull(): void
    {
        self::assertNull(
            StorageRootDeclaration::root(new Config(['storage' => []])),
            'an empty map declares no layout',
        );
    }

    public function testAnEmptyRootAnswersNull(): void
    {
        self::assertNull(
            StorageRootDeclaration::root(new Config(['storage' => ['root' => '']])),
            'the empty string is the silent root, not a declared one',
        );
    }

    public function testADeclaredRootIsAnsweredVerbatim(): void
    {
        self::assertSame(
            '/srv/zef/files',
            StorageRootDeclaration::root(new Config(['storage' => ['root' => '/srv/zef/files']])),
            "the one declared address is the declaration's whole answer",
        );
    }

    public function testANonMapStorageConfigurationRefuses(): void
    {
        try {
            StorageRootDeclaration::root(new Config(['storage' => 'the-checkout']));
            self::fail('A non-map storage configuration must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The storage configuration must be a map, string given.',
                $configException->getMessage(),
            );
        }
    }

    public function testAnUnknownStorageKnobRefusesWithTheKnownListSpelled(): void
    {
        try {
            StorageRootDeclaration::root(new Config([
                'storage' => ['root' => '/srv/zef/files', 'rooot' => '/srv/zef/files'],
            ]));
            self::fail('An unknown storage knob must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                "The storage configuration carries the unknown knob 'rooot' (known knobs: root).",
                $configException->getMessage(),
            );
        }
    }

    public function testAWrongTypedKnobNameRefusesWithItsType(): void
    {
        try {
            StorageRootDeclaration::root(new Config([
                'storage' => ['root' => '/srv/zef/files', 0 => 'a-wrong-typed-key'],
            ]));
            self::fail('A wrong-typed knob name must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                "The storage configuration carries the unknown knob 'int' (known knobs: root).",
                $configException->getMessage(),
            );
        }
    }

    public function testAWrongTypedRootRefuses(): void
    {
        try {
            StorageRootDeclaration::root(new Config(['storage' => ['root' => 42]]));
            self::fail('A wrong-typed storage root must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'storage.root must be a string.',
                $configException->getMessage(),
            );
        }
    }
}
