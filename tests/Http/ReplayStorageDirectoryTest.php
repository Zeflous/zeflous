<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ReplayStorageDirectory;

/**
 * Pins the storage directory's own guard table at the unit seam: every
 * refusal reason the store's battery composes but never isolates -- the
 * symlink shape, the plain-file shape, the other-execute permission bit,
 * and the document-root geometry (equal, inside, sibling) -- plus the
 * resolving answer an acceptable directory earns.
 *
 * @internal
 */
#[CoversClass(ReplayStorageDirectory::class)]
final class ReplayStorageDirectoryTest extends TestCase
{
    private string $directory;

    /** @var array{bool, mixed} whether DOCUMENT_ROOT existed and its previous value */
    private array $previousDocumentRoot = [false, null];

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-replay-dir-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->restoreDocumentRoot();

        foreach (array_reverse($this->nestedPaths()) as $path) {
            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                rmdir($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        if (!is_dir($this->directory)) {
            return;
        }

        $leftovers = glob($this->directory . '/*');
        foreach (\is_array($leftovers) ? $leftovers : [] as $leftover) {
            if (is_file($leftover)) {
                unlink($leftover);
            }
        }

        rmdir($this->directory);
    }

    public function testResolvesAnAcceptableDirectory(): void
    {
        self::assertSame(realpath($this->directory), ReplayStorageDirectory::resolve($this->directory));
    }

    public function testASymlinkedStorageDirectoryIsRefused(): void
    {
        $symlink = $this->directory . '-link';
        symlink($this->directory, $symlink);

        try {
            $this->expectException(InvalidArgumentException::class);

            ReplayStorageDirectory::resolve($symlink);
        } finally {
            unlink($symlink);
        }
    }

    public function testAPlainFileIsRefused(): void
    {
        $file = $this->directory . '/not-a-directory';
        touch($file);
        chmod($file, 0o600);

        $this->expectException(InvalidArgumentException::class);

        ReplayStorageDirectory::resolve($file);
    }

    public function testOtherExecutablePermissionsAreRefused(): void
    {
        chmod($this->directory, 0o701);

        try {
            try {
                ReplayStorageDirectory::resolve($this->directory);
                self::fail('A world-executable storage directory must be refused.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    'Replay storage directory must have owner-only permissions.',
                    $invalidArgumentException->getMessage(),
                );
            }
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    public function testResolvesOutsideAnExplicitDocumentRoot(): void
    {
        $publicRoot = $this->directory . '/public';
        mkdir($publicRoot, 0o700);
        $this->setDocumentRoot($publicRoot);

        self::assertSame(realpath($this->directory), ReplayStorageDirectory::resolve($this->directory));
    }

    public function testRefusesStorageEqualToTheDocumentRoot(): void
    {
        $this->setDocumentRoot($this->directory);

        try {
            ReplayStorageDirectory::resolve($this->directory);
            self::fail('Storage equal to the document root must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'Replay storage must be outside the public document root.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testResolvesASiblingWhoseNameExtendsTheDocumentRoot(): void
    {
        $publicRoot = $this->directory . '/public-root';
        $sibling = $this->directory . '/public-root-evil';
        mkdir($publicRoot, 0o700);
        mkdir($sibling, 0o700);
        $this->setDocumentRoot($publicRoot);

        self::assertSame(realpath($sibling), ReplayStorageDirectory::resolve($sibling));
    }

    /**
     * Points the SAPI document root at the given path, remembering the
     * previous state for tearDown.
     */
    private function setDocumentRoot(string $documentRoot): void
    {
        $this->previousDocumentRoot = [\array_key_exists('DOCUMENT_ROOT', $_SERVER), $_SERVER['DOCUMENT_ROOT'] ?? null];
        $_SERVER['DOCUMENT_ROOT'] = $documentRoot;
    }

    private function restoreDocumentRoot(): void
    {
        [$hadRoot, $previous] = $this->previousDocumentRoot;

        if ($hadRoot) {
            $_SERVER['DOCUMENT_ROOT'] = $previous;
        } else {
            unset($_SERVER['DOCUMENT_ROOT']);
        }
    }

    /**
     * @return list<string> the extra paths the tests may have created under the parent
     */
    private function nestedPaths(): array
    {
        $paths = [];
        foreach (['-link', '/public', '/public-root', '/public-root-evil'] as $suffix) {
            $paths[] = \dirname($this->directory) . '/' . basename($this->directory) . $suffix;
        }

        return $paths;
    }
}
