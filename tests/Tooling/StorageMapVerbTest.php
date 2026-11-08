<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\CommandOutcome;
use Zef\Test\Support\Tooling\StorageMap;

/**
 * The `storage:map` verb as a pure outcome: the argument grammar's own
 * refusals (the unknown flag, the second positional), the one-way
 * root precedence (the positional, then the `ZEF_STORAGE_ROOT`
 * environment spelling, then the repository's own `files` skeleton),
 * the two renderings, and the delegation to the layout's own walk --
 * a refused layout surfacing with the layout's own defect, exactly
 * the words the joined branch spelled before the lift.
 *
 * @internal
 */
#[CoversClass(StorageMap::class)]
#[CoversClass(CommandOutcome::class)]
final class StorageMapVerbTest extends TestCase
{
    private string $previousStorageRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->previousStorageRoot = (string) getenv('ZEF_STORAGE_ROOT');
        putenv('ZEF_STORAGE_ROOT=');
    }

    #[Override]
    protected function tearDown(): void
    {
        putenv('ZEF_STORAGE_ROOT=' . $this->previousStorageRoot);
    }

    public function testAnUnknownFlagIsAUsageDefect(): void
    {
        $outcome = StorageMap::run(['--nope']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame("Usage: bin/zef storage:map [<storage-root>] [--json]\n", $outcome->stderr);
    }

    public function testASecondPositionalIsAUsageDefect(): void
    {
        $outcome = StorageMap::run(['/tmp/one', '/tmp/two']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame("Usage: bin/zef storage:map [<storage-root>] [--json]\n", $outcome->stderr);
    }

    public function testTheRepositorySkeletonAnswersTheDefaultRoot(): void
    {
        $outcome = StorageMap::run([]);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringStartsWith('Storage map -- ', $outcome->stdout);
        self::assertStringContainsString(\dirname(__DIR__, 2) . '/files', $outcome->stdout);
    }

    public function testTheEnvironmentRootAnswersWhenNoPositionalRides(): void
    {
        $root = sys_get_temp_dir() . '/zef-storage-map-verb-' . uniqid('', false);
        foreach (['cache', 'logs', 'queue', 'replay', 'sessions', 'temp', 'uploads'] as $lane) {
            mkdir($root . '/' . $lane, 0o700, true);
        }

        try {
            putenv('ZEF_STORAGE_ROOT=' . $root);
            $fromEnvironment = StorageMap::run([]);

            self::assertSame(0, $fromEnvironment->exitCode);
            self::assertSame('', $fromEnvironment->stderr);
            self::assertStringStartsWith('Storage map -- ', $fromEnvironment->stdout);
            self::assertStringContainsString($root, $fromEnvironment->stdout);
        } finally {
            putenv('ZEF_STORAGE_ROOT=');
            foreach (['cache', 'logs', 'queue', 'replay', 'sessions', 'temp', 'uploads'] as $lane) {
                rmdir($root . '/' . $lane);
            }
            rmdir($root);
        }
    }

    public function testThePositionalWinsOverTheEnvironmentRoot(): void
    {
        putenv('ZEF_STORAGE_ROOT=/does/not/exist');
        $outcome = StorageMap::run([\dirname(__DIR__, 2) . '/files']);

        self::assertSame(0, $outcome->exitCode);
        self::assertStringStartsWith('Storage map -- ', $outcome->stdout);
    }

    public function testTheJsonFlagAnswersTheMachineForm(): void
    {
        $outcome = StorageMap::run([\dirname(__DIR__, 2) . '/files', '--json']);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringEndsWith("\n", $outcome->stdout);

        $decoded = json_decode(mb_rtrim($outcome->stdout, "\n"), true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('root', $decoded);
        self::assertArrayHasKey('lanes', $decoded);
        self::assertArrayHasKey('count', $decoded);
    }

    public function testAMissingRootSurfacesTheLayoutRefusalNamingTheDefect(): void
    {
        $outcome = StorageMap::run(['/does/not/exist']);

        self::assertSame(1, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(
            "The storage layout refused: The storage root must be an existing writable non-symlink directory.\n",
            $outcome->stderr,
        );
    }
}
