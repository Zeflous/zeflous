<?php

declare(strict_types=1);

namespace Zef\Test\Storage;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Storage\StorageDocumentRoot;
use Zef\Framework\Storage\StorageRoot;

/**
 * Pins the storage root's own guard table at the unit seam: every
 * refusal reason the layout's walk composes but never isolates --
 * the empty spelling, the symlink shape, the plain-file shape, the
 * missing directory, the unwritable directory, the unresolvable
 * document root, and the document-root geometry (equal, inside,
 * sibling whose name merely extends) -- plus the resolving answer
 * an acceptable root earns and the corrupt-bag readings the SAPI
 * state can carry. The document-root geometry rides the fold's own
 * seam ({@see StorageDocumentRoot}), covered through this battery.
 *
 * @internal
 */
#[CoversClass(StorageRoot::class)]
#[CoversClass(StorageDocumentRoot::class)]
final class StorageRootTest extends TestCase
{
    private string $directory;

    /** @var array{bool, mixed} whether DOCUMENT_ROOT existed and its previous value */
    private array $previousDocumentRoot = [false, null];

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-storage-root-' . bin2hex(random_bytes(8));
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

        rmdir($this->directory);
    }

    public function testResolvesAnAcceptableRoot(): void
    {
        self::assertSame(realpath($this->directory), StorageRoot::resolve($this->directory));
    }

    public function testTheEmptyRootSpellingIsRefused(): void
    {
        try {
            StorageRoot::resolve('');
            self::fail('An empty storage root spelling must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The storage root must be an existing writable non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testASymlinkedRootIsRefused(): void
    {
        $symlink = $this->directory . '-link';
        symlink($this->directory, $symlink);

        try {
            try {
                StorageRoot::resolve($symlink);
                self::fail('A symlinked storage root must be refused.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    'The storage root must be an existing writable non-symlink directory.',
                    $invalidArgumentException->getMessage(),
                );
            }
        } finally {
            unlink($symlink);
        }
    }

    public function testAPlainFileIsRefused(): void
    {
        $file = $this->directory . '/not-a-directory';
        touch($file);
        chmod($file, 0o600);

        try {
            try {
                StorageRoot::resolve($file);
                self::fail('A plain-file storage root must be refused.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    'The storage root must be an existing writable non-symlink directory.',
                    $invalidArgumentException->getMessage(),
                );
            }
        } finally {
            unlink($file);
        }
    }

    public function testAMissingRootIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StorageRoot::resolve($this->directory . '/never-created');
    }

    public function testAnUnwritableRootIsRefused(): void
    {
        chmod($this->directory, 0o500);

        try {
            try {
                StorageRoot::resolve($this->directory);
                self::fail('An unwritable storage root must be refused.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    'The storage root must be an existing writable non-symlink directory.',
                    $invalidArgumentException->getMessage(),
                );
            }
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    public function testAnUnresolvableDocumentRootIsRefused(): void
    {
        $this->setDocumentRoot($this->directory . '/no-such-public-root');

        try {
            StorageRoot::resolve($this->directory);
            self::fail('A declared but unresolvable document root must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The public document root cannot be resolved safely.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARootEqualToTheDocumentRootIsRefused(): void
    {
        $this->setDocumentRoot($this->directory);

        try {
            StorageRoot::resolve($this->directory);
            self::fail('A storage root equal to the document root must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The storage root must be outside the public document root.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARootInsideTheDocumentRootIsRefused(): void
    {
        $publicRoot = $this->directory . '/public';
        $insideRoot = $publicRoot . '/files';
        mkdir($publicRoot, 0o700);
        mkdir($insideRoot, 0o700);
        $this->setDocumentRoot($publicRoot);

        try {
            StorageRoot::resolve($insideRoot);
            self::fail('A storage root inside the document root must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The storage root must be outside the public document root.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testASiblingWhoseNameExtendsTheDocumentRootResolves(): void
    {
        $publicRoot = $this->directory . '/public-root';
        $sibling = $this->directory . '/public-root-evil';
        mkdir($publicRoot, 0o700);
        mkdir($sibling, 0o700);
        $this->setDocumentRoot($publicRoot);

        self::assertSame(realpath($sibling), StorageRoot::resolve($sibling));
    }

    public function testARootOutsideAnExplicitDocumentRootResolves(): void
    {
        $publicRoot = $this->directory . '/public';
        mkdir($publicRoot, 0o700);
        $this->setDocumentRoot($publicRoot);

        self::assertSame(realpath($this->directory), StorageRoot::resolve($this->directory));
    }

    public function testACorruptDocumentRootEntryReadsAsAbsent(): void
    {
        $this->previousDocumentRoot = [\array_key_exists('DOCUMENT_ROOT', $_SERVER), $_SERVER['DOCUMENT_ROOT'] ?? null];
        $_SERVER['DOCUMENT_ROOT'] = ['corrupt'];

        self::assertSame(realpath($this->directory), StorageRoot::resolve($this->directory));
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
        $suffixes = [
            '-link',
            '/public',
            '/public/files',
            '/public-root',
            '/public-root-evil',
            '/not-a-directory',
        ];

        $paths = [];
        foreach ($suffixes as $suffix) {
            $paths[] = \dirname($this->directory) . '/' . basename($this->directory) . $suffix;
        }

        return $paths;
    }
}
