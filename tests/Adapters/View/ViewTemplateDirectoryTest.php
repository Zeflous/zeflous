<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\View;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\View\ViewTemplateDirectory;
use Zef\Test\Support\Adapters\View\ViewFileDoubleState;

/**
 * Pins the templates root's own posture: the geometry (existing,
 * readable, non-symlink, resolvable), the owner-only permission
 * mask, and the document-root exclusion through the storage layer's
 * own fold -- every refusal naming the root's own words, except the
 * document-root judgement the shared fold owns.
 *
 * @internal
 */
#[CoversClass(ViewTemplateDirectory::class)]
final class ViewTemplateDirectoryTest extends TestCase
{
    private string $directory;

    /** @var array{bool, mixed} whether DOCUMENT_ROOT existed and its previous value */
    private array $previousDocumentRoot = [false, null];

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-view-root-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        ViewFileDoubleState::reset();

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
            ViewTemplateDirectory::resolve($this->directory),
        );
    }

    public function testResolvesATrailingSlashSpellingCanonically(): void
    {
        self::assertSame(
            realpath($this->directory),
            ViewTemplateDirectory::resolve($this->directory . '/'),
        );
    }

    public function testTheEmptyPathIsRefused(): void
    {
        try {
            ViewTemplateDirectory::resolve('');
            self::fail('The empty path was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The view templates root must be an existing readable'
                . ' non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAMissingDirectoryIsRefused(): void
    {
        try {
            ViewTemplateDirectory::resolve($this->directory . '-missing');
            self::fail('The missing directory was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The view templates root must be an existing readable'
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
            ViewTemplateDirectory::resolve($file);
            self::fail('The plain file was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The view templates root must be an existing readable'
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
            ViewTemplateDirectory::resolve($symlink);
            self::fail('The symlinked directory was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The view templates root must be an existing readable'
                . ' non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnUnreadableDirectoryIsRefused(): void
    {
        chmod($this->directory, 0o300);

        try {
            ViewTemplateDirectory::resolve($this->directory);
            self::fail('The unreadable directory was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The view templates root must be an existing readable'
                . ' non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    public function testAnUnresolvableDirectoryIsRefused(): void
    {
        ViewFileDoubleState::$realpathAnswer = false;

        try {
            ViewTemplateDirectory::resolve($this->directory);
            self::fail('The unresolvable directory was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The view templates root cannot be resolved.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnUnreadablePermissionMaskIsRefused(): void
    {
        ViewFileDoubleState::$filepermsAnswer = false;

        try {
            ViewTemplateDirectory::resolve($this->directory);
            self::fail('The unreadable permission mask was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The view templates root must have owner-only permissions.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAGroupReadableDirectoryIsRefused(): void
    {
        chmod($this->directory, 0o770);

        try {
            ViewTemplateDirectory::resolve($this->directory);
            self::fail('The group-readable directory was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The view templates root must have owner-only permissions.',
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
            ViewTemplateDirectory::resolve($this->directory);
            self::fail('The world-executable directory was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The view templates root must have owner-only permissions.',
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
            ViewTemplateDirectory::resolve($this->directory),
        );
    }

    public function testRefusesARootEqualToTheDocumentRoot(): void
    {
        $this->setDocumentRoot($this->directory);

        try {
            ViewTemplateDirectory::resolve($this->directory);
            self::fail('A root equal to the document root must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The storage root must be outside the public document root.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testRefusesARootInsideTheDocumentRoot(): void
    {
        $publicRoot = $this->directory . '/public';
        $insideRoot = $publicRoot . '/templates';
        mkdir($publicRoot, 0o700);
        mkdir($insideRoot, 0o700);
        $this->setDocumentRoot($publicRoot);

        try {
            ViewTemplateDirectory::resolve($insideRoot);
            self::fail('A root inside the document root must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The storage root must be outside the public document root.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    private function setDocumentRoot(string $documentRoot): void
    {
        $this->previousDocumentRoot = [
            \array_key_exists('DOCUMENT_ROOT', $_SERVER),
            $_SERVER['DOCUMENT_ROOT'] ?? null,
        ];
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

    private function removeTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        foreach (\is_array($entries) ? $entries : [] as $base) {
            if ($base === '.' || $base === '..') {
                continue;
            }

            $entry = $directory . \DIRECTORY_SEPARATOR . $base;

            if (is_dir($entry)) {
                $this->removeTree($entry);
            } else {
                unlink($entry);
            }
        }

        rmdir($directory);
    }
}
