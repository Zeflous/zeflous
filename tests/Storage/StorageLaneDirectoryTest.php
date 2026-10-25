<?php

declare(strict_types=1);

namespace Zef\Test\Storage;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Storage\StorageLane;
use Zef\Framework\Storage\StorageLaneDirectory;

/**
 * Pins the per-lane fold's own guard table: the missing lane, the
 * plain-file lane, the symlinked lane and the unwritable lane each
 * refuse WITH THE LANE NAMED -- the operator-facing contract the
 * layout's walk inherits -- plus the resolving answer an acceptable
 * lane earns and the canonical address a trailing-slash root
 * spelling still walks to.
 *
 * @internal
 */
#[CoversClass(StorageLaneDirectory::class)]
final class StorageLaneDirectoryTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zef-storage-lane-' . bin2hex(random_bytes(8));
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
            if (is_link($entry)) {
                unlink($entry);
            } elseif (is_dir($entry)) {
                rmdir($entry);
            } elseif (is_file($entry)) {
                unlink($entry);
            }
        }

        rmdir($this->root);
    }

    public function testResolvesEveryLaneUnderTheRoot(): void
    {
        foreach (StorageLane::cases() as $lane) {
            self::assertSame(
                realpath($this->root . '/' . $lane->spelling()),
                StorageLaneDirectory::resolve($this->root, $lane),
            );
        }
    }

    public function testAMissingLaneIsRefusedWithTheLaneNamed(): void
    {
        rmdir($this->root . '/uploads');

        try {
            StorageLaneDirectory::resolve($this->root, StorageLane::Uploads);
            self::fail('A missing storage lane must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The storage lane 'uploads' must be an existing writable non-symlink directory.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAPlainFileLaneIsRefusedWithTheLaneNamed(): void
    {
        $file = $this->root . '/cache';
        rmdir($file);
        touch($file);
        chmod($file, 0o600);

        try {
            try {
                StorageLaneDirectory::resolve($this->root, StorageLane::Cache);
                self::fail('A plain-file storage lane must be refused.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    "The storage lane 'cache' must be an existing writable non-symlink directory.",
                    $invalidArgumentException->getMessage(),
                );
            }
        } finally {
            unlink($file);
        }
    }

    public function testASymlinkedLaneIsRefusedWithTheLaneNamed(): void
    {
        $lane = $this->root . '/temp';
        $target = $this->root . '/logs';
        rmdir($lane);
        symlink($target, $lane);

        try {
            try {
                StorageLaneDirectory::resolve($this->root, StorageLane::Temp);
                self::fail('A symlinked storage lane must be refused.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    "The storage lane 'temp' must be an existing writable non-symlink directory.",
                    $invalidArgumentException->getMessage(),
                );
            }
        } finally {
            unlink($lane);
        }
    }

    public function testAnUnwritableLaneIsRefusedWithTheLaneNamed(): void
    {
        chmod($this->root . '/replay', 0o500);

        try {
            try {
                StorageLaneDirectory::resolve($this->root, StorageLane::Replay);
                self::fail('An unwritable storage lane must be refused.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    "The storage lane 'replay' must be an existing writable non-symlink directory.",
                    $invalidArgumentException->getMessage(),
                );
            }
        } finally {
            chmod($this->root . '/replay', 0o700);
        }
    }

    public function testATrailingSlashRootSpellingStillAnswersTheCanonicalLane(): void
    {
        self::assertSame(
            realpath($this->root . '/logs'),
            StorageLaneDirectory::resolve($this->root . '/', StorageLane::Logs),
        );
    }
}
