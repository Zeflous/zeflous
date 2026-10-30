<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Middleware\ErrorStorageWiring;
use Zef\Framework\Storage\StorageLane;

/**
 * Pins the error family's wiring onto the storage layout: the
 * precedence (an explicitly spelled address always wins over the
 * declared root's derivation), the no-sink posture an absent or
 * silent storage map keeps, the strict read of the `storage` map
 * (not a map, an unknown knob, a wrong-typed root -- each refused
 * with its own name), and the walk itself riding the layout's own
 * fail-closed verdicts (the refusing seam named, never a partial
 * map). The shipped profile's own declaration of the family's one
 * knob is pinned here too, so the `storage.php` file and the fold's
 * strict census cannot drift apart.
 *
 * @internal
 */
#[CoversClass(ErrorStorageWiring::class)]
final class ErrorStorageWiringTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zef-error-storage-' . bin2hex(random_bytes(8));
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

    public function testAnExplicitLogPathWinsOverTheDeclaredRoot(): void
    {
        $explicit = $this->root . '/an-explicit-address.jsonl';

        self::assertSame(
            $explicit,
            ErrorStorageWiring::logAddress(new Config([
                'http' => ['error_handling' => ['log_path' => $explicit]],
                'storage' => ['root' => $this->root],
            ])),
            "the deployment's own words are never overridden by the layout",
        );
    }

    public function testAnEmptyLogPathDerivesTheLogsLaneAddress(): void
    {
        self::assertSame(
            $this->root . '/logs/' . ErrorStorageWiring::LOG_FILE_NAME,
            ErrorStorageWiring::logAddress(new Config([
                'http' => ['error_handling' => ['log_path' => '']],
                'storage' => ['root' => $this->root],
            ])),
            'the empty knob falls back to the logs lane the root provisions',
        );
    }

    public function testAnAbsentStorageMapKeepsTheNoSinkPosture(): void
    {
        self::assertSame(
            '',
            ErrorStorageWiring::logAddress(new Config([
                'http' => ['error_handling' => ['log_path' => '']],
            ])),
            'no declared root keeps the ad-hoc posture unchanged',
        );
    }

    public function testAnEmptyRootKeepsTheNoSinkPosture(): void
    {
        self::assertSame(
            '',
            ErrorStorageWiring::logAddress(new Config([
                'http' => ['error_handling' => ['log_path' => '']],
                'storage' => ['root' => ''],
            ])),
            'a silent root knob declares no layout',
        );
    }

    public function testAWrongTypedLogPathRefusesWithTheFamilyMessage(): void
    {
        try {
            ErrorStorageWiring::logAddress(new Config([
                'http' => ['error_handling' => ['log_path' => 42]],
            ]));
            self::fail('A wrong-typed log address must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'http.error_handling.log_path must be a string.',
                $configException->getMessage(),
            );
        }
    }

    public function testAWrongTypedRootRefuses(): void
    {
        try {
            ErrorStorageWiring::logAddress(new Config([
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
            ErrorStorageWiring::replayDirectory(new Config([
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
            ErrorStorageWiring::logAddress(new Config([
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

    public function testAMissingRootKeyAnswersTheSilentPosture(): void
    {
        self::assertSame(
            '',
            ErrorStorageWiring::replayDirectory(new Config(['storage' => []])),
            'an empty storage map declares no layout',
        );
    }

    public function testAMisprovisionedRootRefusesAtTheWalk(): void
    {
        try {
            ErrorStorageWiring::logAddress(new Config([
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

    public function testAMissingLaneRefusesNamingTheLane(): void
    {
        rmdir($this->root . '/' . StorageLane::Cache->spelling());

        try {
            ErrorStorageWiring::replayDirectory(new Config([
                'storage' => ['root' => $this->root],
            ]));
            self::fail('A missing lane must refuse the walk.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The storage lane 'cache' must be an existing writable non-symlink directory.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheReplayDirectoryOfferAnswersTheReplayLane(): void
    {
        self::assertSame(
            $this->root . '/replay',
            ErrorStorageWiring::replayDirectory(new Config([
                'storage' => ['root' => $this->root],
            ])),
            'the replay lane is the offer the replay knob falls back to',
        );
    }

    public function testTheReplayDirectoryOfferStaysEmptyWithoutARoot(): void
    {
        self::assertSame(
            '',
            ErrorStorageWiring::replayDirectory(new Config([])),
            'no root declared, no offer to make',
        );
    }

    public function testTheShippedProfileDeclaresTheStorageFamilysOneKnob(): void
    {
        $profile = require ZEF_FRAMEWORK_ROOT . '/app/configs/storage.php';

        self::assertIsArray($profile, 'the shipped profile is a plain PHP map');
        self::assertSame(
            ['root' => ''],
            $profile,
            'the storage family ships exactly its one silent knob',
        );
    }
}
