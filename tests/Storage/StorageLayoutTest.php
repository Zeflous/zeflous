<?php

declare(strict_types=1);

namespace Zef\Test\Storage;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Storage\StorageLane;
use Zef\Framework\Storage\StorageLayout;
use Zef\Framework\Storage\StoragePaths;

/**
 * Pins the resolution walk end to end: a provisioned tree answers
 * the whole map -- the root every lane joins onto, every lane's
 * canonical address, the declaration order held -- a missing lane
 * stops the walk at the FIRST refusal (never a partial map), and
 * the answered record's own three readings (root, typed path,
 * spelling-keyed map) agree with each other.
 *
 * @internal
 */
#[CoversClass(StorageLayout::class)]
#[CoversClass(StoragePaths::class)]
final class StorageLayoutTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zef-storage-layout-' . bin2hex(random_bytes(8));
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

    public function testAProvisionedTreeAnswersTheWholeMap(): void
    {
        $paths = StorageLayout::resolve($this->root);

        self::assertSame(realpath($this->root), $paths->root());

        foreach (StorageLane::cases() as $lane) {
            self::assertSame(
                realpath($this->root . '/' . $lane->spelling()),
                $paths->path($lane),
            );
        }
    }

    public function testTheMapHoldsTheVocabularysDeclarationOrder(): void
    {
        self::assertSame(
            ['logs', 'cache', 'temp', 'uploads', 'replay', 'sessions', 'queue'],
            array_keys(StorageLayout::resolve($this->root)->lanes()),
        );
    }

    public function testTheTypedPathAndTheSpellingKeyedMapAgree(): void
    {
        $paths = StorageLayout::resolve($this->root);

        foreach ($paths->lanes() as $spelling => $address) {
            self::assertSame($address, $paths->path(StorageLane::from($spelling)));
        }
    }

    public function testAMissingLaneStopsTheWalkAtTheFirstRefusal(): void
    {
        rmdir($this->root . '/temp');

        try {
            StorageLayout::resolve($this->root);
            self::fail('A tree with a missing lane must refuse the whole walk.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The storage lane 'temp' must be an existing writable non-symlink directory.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAMissingRootRefusesBeforeAnyLaneIsWalked(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StorageLayout::resolve($this->root . '/never-created');
    }

    public function testTheWalkStopsAtTheFirstRefusingLaneInDeclarationOrder(): void
    {
        rmdir($this->root . '/cache');
        rmdir($this->root . '/uploads');

        try {
            StorageLayout::resolve($this->root);
            self::fail('The walk must stop at the first refusing lane, in declaration order.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The storage lane 'cache' must be an existing writable non-symlink directory.",
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
