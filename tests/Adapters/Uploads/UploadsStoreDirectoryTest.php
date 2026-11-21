<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Uploads;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Uploads\UploadsStoreDirectory;
use Zef\Test\Support\Adapters\Uploads\UploadsFileDoubleState;

/**
 * Pins the uploads store's own directory posture: the geometry
 * (existing, writable, non-symlink, resolvable), the owner-only
 * permission mask, and the document-root exclusion through the
 * storage layer's own fold -- every refusal naming the store's
 * own words, except the document-root judgement the shared fold
 * owns. The lane is the one Partial area the priorities table
 * spells as a production attack surface, so the posture's arms
 * ride the session family's own directory-test shape.
 *
 * @internal
 */
#[CoversClass(UploadsStoreDirectory::class)]
final class UploadsStoreDirectoryTest extends TestCase
{
    private string $directory;

    /** @var array{bool, mixed} whether DOCUMENT_ROOT existed and its previous value */
    private array $previousDocumentRoot = [false, null];

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-uploads-store-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        UploadsFileDoubleState::reset();

        $this->restoreDocumentRoot();

        if (is_link($this->directory . '-link')) {
            unlink($this->directory . '-link');
        }

        $this->removeTree($this->directory);
    }

    public function testResolvesAnAcceptableDirectory(): void
    {
        self::assertSame(
            realpath($this->directory),
            UploadsStoreDirectory::resolve($this->directory),
        );
    }

    public function testTheEmptyPathIsRefused(): void
    {
        try {
            UploadsStoreDirectory::resolve('');
            self::fail('The empty path was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads file store must be an existing writable'
                . ' non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAMissingDirectoryIsRefused(): void
    {
        try {
            UploadsStoreDirectory::resolve($this->directory . '-missing');
            self::fail('The missing directory was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads file store must be an existing writable'
                . ' non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAPlainFileIsRefused(): void
    {
        $file = $this->directory . '-file';
        file_put_contents($file, 'not a directory');

        try {
            UploadsStoreDirectory::resolve($file);
            self::fail('The plain file was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads file store must be an existing writable'
                . ' non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        } finally {
            unlink($file);
        }
    }

    public function testASymlinkedDirectoryIsRefused(): void
    {
        $symlink = $this->directory . '-link';
        symlink($this->directory, $symlink);

        try {
            UploadsStoreDirectory::resolve($symlink);
            self::fail('The symlinked directory was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads file store must be an existing writable'
                . ' non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnUnwritableDirectoryIsRefused(): void
    {
        chmod($this->directory, 0o500);

        try {
            UploadsStoreDirectory::resolve($this->directory);
            self::fail('The unwritable directory was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads file store must be an existing writable'
                . ' non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    public function testAnUnresolvableDirectoryIsRefused(): void
    {
        UploadsFileDoubleState::$realpathAnswer = false;

        try {
            UploadsStoreDirectory::resolve($this->directory);
            self::fail('The unresolvable directory was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads file store directory cannot be resolved.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnUnreadablePermissionMaskIsRefused(): void
    {
        UploadsFileDoubleState::$filepermsAnswer = false;

        try {
            UploadsStoreDirectory::resolve($this->directory);
            self::fail('The unreadable permission mask was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads file store directory must have owner-only'
                . ' permissions.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAGroupReadableDirectoryIsRefused(): void
    {
        chmod($this->directory, 0o770);

        try {
            UploadsStoreDirectory::resolve($this->directory);
            self::fail('The group-readable directory was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads file store directory must have owner-only'
                . ' permissions.',
                $invalidArgumentException->getMessage(),
            );
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    public function testAWorldExecutableDirectoryIsRefused(): void
    {
        chmod($this->directory, 0o701);

        try {
            UploadsStoreDirectory::resolve($this->directory);
            self::fail('The world-executable directory was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The uploads file store directory must have owner-only'
                . ' permissions.',
                $invalidArgumentException->getMessage(),
            );
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    public function testResolvesOutsideAnExplicitDocumentRoot(): void
    {
        $publicRoot = $this->directory . '/public';
        mkdir($publicRoot, 0o700);
        $this->setDocumentRoot($publicRoot);

        self::assertSame(
            realpath($this->directory),
            UploadsStoreDirectory::resolve($this->directory),
        );
    }

    public function testRefusesAStoreEqualToTheDocumentRoot(): void
    {
        $this->setDocumentRoot($this->directory);

        try {
            UploadsStoreDirectory::resolve($this->directory);
            self::fail('A store equal to the document root must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The storage root must be outside the public document root.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesAStoreInsideTheDocumentRoot(): void
    {
        $publicRoot = $this->directory . '/public';
        $insideRoot = $publicRoot . '/uploads';
        mkdir($publicRoot, 0o700);
        mkdir($insideRoot, 0o700);
        $this->setDocumentRoot($publicRoot);

        try {
            UploadsStoreDirectory::resolve($insideRoot);
            self::fail('A store inside the document root must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The storage root must be outside the public document root.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    /**
     * Arms the SAPI document root the shared fold reads.
     *
     * @param string $documentRoot the public document root the fold
     *                             judges against
     */
    private function setDocumentRoot(string $documentRoot): void
    {
        $this->previousDocumentRoot = [
            \array_key_exists('DOCUMENT_ROOT', $_SERVER),
            $_SERVER['DOCUMENT_ROOT'] ?? null,
        ];

        $_SERVER['DOCUMENT_ROOT'] = $documentRoot;
    }

    /**
     * Restores the SAPI document root the test may have armed.
     */
    private function restoreDocumentRoot(): void
    {
        [$existed, $previous] = $this->previousDocumentRoot;

        if ($existed) {
            $_SERVER['DOCUMENT_ROOT'] = $previous;
        } else {
            unset($_SERVER['DOCUMENT_ROOT']);
        }
    }

    /**
     * Removes a directory tree the test laid down, best-effort: a
     * tree the runner could not remove is the runner's own
     * environment, never a reason to redden the arm's own verdict.
     */
    private function removeTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        foreach (\is_array($entries) ? $entries : [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
