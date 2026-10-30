<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Middleware\SessionStorageWiring;
use Zef\Framework\Storage\StorageLane;

/**
 * Pins the session family's wiring onto the storage layout: the
 * precedence (an explicitly spelled address always wins over the
 * declared root's derivation), the no-directory posture an absent
 * or silent storage map keeps, the strict read of the `storage`
 * map (not a map, an unknown knob, a wrong-typed root -- each
 * refused with its own name), and the walk itself riding the
 * layout's own fail-closed verdicts (the refusing seam named,
 * never a partial map) over the `sessions` lane the vocabulary
 * gained with its first writer.
 *
 * @internal
 */
#[CoversClass(SessionStorageWiring::class)]
final class SessionStorageWiringTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zef-session-storage-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0o700);

        foreach (StorageLane::cases() as $lane) {
            mkdir($this->root . '/' . $lane->spelling(), 0o700);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $entries = glob($this->root . '/*');
        foreach (\is_array($entries) ? $entries : [] as $entry) {
            if (is_dir($entry)) {
                rmdir($entry);
            } elseif (is_file($entry)) {
                unlink($entry);
            }
        }

        rmdir($this->root);
    }

    public function testAnExplicitPathWinsOverTheDeclaredRoot(): void
    {
        $explicit = $this->root . '/an-explicit-session-store';

        self::assertSame(
            $explicit,
            SessionStorageWiring::directory($explicit, new Config([
                'storage' => ['root' => $this->root],
            ])),
            "the deployment's own words are never overridden by the layout",
        );
    }

    public function testAnEmptyPathDerivesTheSessionsLaneAddress(): void
    {
        self::assertSame(
            $this->root . '/sessions',
            SessionStorageWiring::directory('', new Config([
                'storage' => ['root' => $this->root],
            ])),
            'the empty knob falls back to the sessions lane the root provisions',
        );
    }

    public function testAnAbsentStorageMapKeepsTheNoDirectoryPosture(): void
    {
        self::assertSame(
            '',
            SessionStorageWiring::directory('', new Config([])),
            'no declared root keeps the combination refusal the factory owns',
        );
    }

    public function testAnEmptyRootKeepsTheNoDirectoryPosture(): void
    {
        self::assertSame(
            '',
            SessionStorageWiring::directory('', new Config([
                'storage' => ['root' => ''],
            ])),
            'a silent root knob declares no layout',
        );
    }

    public function testAWrongTypedRootRefuses(): void
    {
        try {
            SessionStorageWiring::directory('', new Config([
                'storage' => ['root' => 42],
            ]));
            self::fail('A wrong-typed storage root must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'storage.root must be a string.',
                $configException->getMessage(),
            );
        }
    }

    public function testANonMapStorageConfigurationRefuses(): void
    {
        try {
            SessionStorageWiring::directory('', new Config([
                'storage' => 'the-checkout',
            ]));
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
            SessionStorageWiring::directory('', new Config([
                'storage' => ['root' => $this->root, 'rooot' => $this->root],
            ]));
            self::fail('An unknown storage knob must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                "The storage configuration carries the unknown knob 'rooot' (known knobs: root).",
                $configException->getMessage(),
            );
        }
    }

    public function testAMissingRootKeyAnswersTheNoDirectoryPosture(): void
    {
        self::assertSame(
            '',
            SessionStorageWiring::directory('', new Config(['storage' => []])),
            'an empty storage map declares no layout',
        );
    }

    public function testAMisprovisionedRootRefusesAtTheWalk(): void
    {
        try {
            SessionStorageWiring::directory('', new Config([
                'storage' => ['root' => $this->root . '/a-root-that-is-absent'],
            ]));
            self::fail('A misprovisioned root must refuse at the walk.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The storage root must be an existing writable non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAMissingSessionsLaneRefusesNamingTheLane(): void
    {
        rmdir($this->root . '/' . StorageLane::Sessions->spelling());

        try {
            SessionStorageWiring::directory('', new Config([
                'storage' => ['root' => $this->root],
            ]));
            self::fail('A missing sessions lane must refuse the walk.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The storage lane 'sessions' must be an existing writable non-symlink directory.",
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
