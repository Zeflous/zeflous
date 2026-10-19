<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ReplayStorageDirectory;

/**
 * The replay storage directory's own refusal grammar, one discriminating
 * case per clause: what the directory itself must be (existing, writable,
 * neither a symlink nor a plain file), the owner-only permission mask
 * down to the single sticky other-execute bit, and the document-root
 * boundary in all four directions -- equal to the root, nested inside,
 * a sibling that only shares the string prefix, and the CLI runtime
 * that carries no document root at all.
 *
 * @internal
 */
#[CoversClass(ReplayStorageDirectory::class)]
final class ReplayStorageDirectoryTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        unset($_SERVER['DOCUMENT_ROOT']);
        $this->directory = sys_get_temp_dir() . '/zef-storage-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        rmdir($this->directory);

        if (!\array_key_exists('DOCUMENT_ROOT', $_SERVER)) {
            return;
        }

        unset($_SERVER['DOCUMENT_ROOT']);
    }

    public function testASymlinkToAValidDirectoryIsRefused(): void
    {
        $link = $this->directory . '-link';
        self::assertTrue(symlink($this->directory, $link));

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessageIsOrContains(
                'Replay storage must be an existing writable non-symlink directory.',
            );
            ReplayStorageDirectory::resolve($link);
        } finally {
            unlink($link);
        }
    }

    public function testARegularFileIsRefusedEvenWhenWritable(): void
    {
        $file = $this->directory . '/plain-file';
        self::assertTrue(touch($file));

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessageIsOrContains(
                'Replay storage must be an existing writable non-symlink directory.',
            );
            ReplayStorageDirectory::resolve($file);
        } finally {
            unlink($file);
        }
    }

    public function testTheOtherExecuteBitAloneIsRefused(): void
    {
        self::assertTrue(chmod($this->directory, 0o701));

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessageIsOrContains('Replay storage directory must have owner-only permissions.');
            ReplayStorageDirectory::resolve($this->directory);
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    public function testTheStorageDirectoryCannotBeTheDocumentRootItself(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = $this->directory;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Replay storage must be outside the public document root.');
        ReplayStorageDirectory::resolve($this->directory);
    }

    public function testStorageOutsideTheDocumentRootResolves(): void
    {
        $publicRoot = $this->directory . '/public';
        self::assertTrue(mkdir($publicRoot, 0o700));
        $_SERVER['DOCUMENT_ROOT'] = $publicRoot;

        self::assertSame($this->directory, ReplayStorageDirectory::resolve($this->directory));

        rmdir($publicRoot);
    }

    public function testASiblingThatOnlySharesTheStringPrefixIsAccepted(): void
    {
        $publicRoot = $this->directory . '/root';
        $sibling = $this->directory . '/root-sibling';
        self::assertTrue(mkdir($publicRoot, 0o700));
        self::assertTrue(mkdir($sibling, 0o700));
        $_SERVER['DOCUMENT_ROOT'] = $publicRoot;

        try {
            self::assertSame($sibling, ReplayStorageDirectory::resolve($sibling));
        } finally {
            rmdir($sibling);
            rmdir($publicRoot);
        }
    }

    public function testARuntimeWithoutADocumentRootLeavesTheBoundaryOpen(): void
    {
        unset($_SERVER['DOCUMENT_ROOT']);

        $underCwd = getcwd() . '/zef-storage-boundary-' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($underCwd, 0o700));

        try {
            self::assertSame($underCwd, ReplayStorageDirectory::resolve($underCwd));
        } finally {
            rmdir($underCwd);
        }
    }
}
