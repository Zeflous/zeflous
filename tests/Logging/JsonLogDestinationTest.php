<?php

declare(strict_types=1);

namespace Zef\Test\Logging;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Logging\JsonLogDestination;
use Zef\Framework\Logging\JsonLogDocumentRoot;
use Zef\Framework\Logging\JsonLogFilePrivacy;

/**
 * The JSON log destination's refusal grammar with one exact-message case
 * per clause -- the NUL byte, the empty path, the directory, the dot
 * filenames, the missing parent, and the loose existing file -- because
 * a mutant that skips one guard usually still dies later against a
 * different check: only the message pins WHICH refusal fired. The
 * document-root boundary underneath gets its own four directions,
 * including the boundary that the runtime carries no root at all.
 *
 * @internal
 */
#[CoversClass(JsonLogDestination::class)]
#[CoversClass(JsonLogDocumentRoot::class)]
#[CoversClass(JsonLogFilePrivacy::class)]
final class JsonLogDestinationTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        unset($_SERVER['DOCUMENT_ROOT']);
        $this->directory = sys_get_temp_dir() . '/zef-logdst-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob($this->directory . '/*');
        foreach (\is_array($files) ? $files : [] as $file) {
            if (!is_file($file) && !is_link($file)) {
                continue;
            }

            unlink($file);
        }

        if (!is_dir($this->directory)) {
            return;
        }

        rmdir($this->directory);

        if (!\array_key_exists('DOCUMENT_ROOT', $_SERVER)) {
            return;
        }

        unset($_SERVER['DOCUMENT_ROOT']);
    }

    public function testANulByteInThePathIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            'The JSON log path must be a non-symlink file in an existing directory.',
        );
        JsonLogDestination::resolve($this->directory . "/a\0b.jsonl");
    }

    public function testTheEmptyPathIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            'The JSON log path must be a non-symlink file in an existing directory.',
        );
        JsonLogDestination::resolve('');
    }

    public function testADirectoryIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            'The JSON log path must be a non-symlink file in an existing directory.',
        );
        JsonLogDestination::resolve($this->directory);
    }

    public function testDotFilenamesAreRefused(): void
    {
        $file = $this->directory . '/target';
        self::assertTrue(touch($file));

        try {
            JsonLogDestination::resolve($file . '/.');
            self::fail('A dot filename must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The JSON log path must be a file in an existing directory.',
                $invalidArgumentException->getMessage(),
            );
        }

        try {
            JsonLogDestination::resolve($file . '/..');
            self::fail('A dot-dot filename must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The JSON log path must be a file in an existing directory.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAMissingParentDirectoryIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The JSON log path must be a file in an existing directory.');
        JsonLogDestination::resolve($this->directory . '-missing/log.jsonl');
    }

    public function testAnExistingLooseFileIsRefused(): void
    {
        $loose = $this->directory . '/loose.jsonl';
        self::assertTrue(touch($loose));
        self::assertTrue(chmod($loose, 0o644));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(
            'The existing JSON log file must be writable with owner-only permissions.',
        );
        JsonLogDestination::resolve($loose);
    }

    public function testTheDestinationCannotBeTheDocumentRootItself(): void
    {
        $_SERVER['DOCUMENT_ROOT'] = $this->directory;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The JSON log destination must be outside the public document root.');
        JsonLogDocumentRoot::assertDestinationIsOutside($this->directory);
    }

    public function testADestinationOutsideTheDocumentRootIsAccepted(): void
    {
        $publicRoot = $this->directory . '/public';
        self::assertTrue(mkdir($publicRoot, 0o700));
        $_SERVER['DOCUMENT_ROOT'] = $publicRoot;

        try {
            JsonLogDocumentRoot::assertDestinationIsOutside($this->directory . '/app.jsonl');
            self::assertDirectoryExists($publicRoot, 'the boundary accepted an outside destination');
        } finally {
            rmdir($publicRoot);
        }
    }

    public function testASiblingThatOnlySharesTheStringPrefixIsAccepted(): void
    {
        $publicRoot = $this->directory . '/root';
        self::assertTrue(mkdir($publicRoot, 0o700));
        $_SERVER['DOCUMENT_ROOT'] = $publicRoot;

        try {
            JsonLogDocumentRoot::assertDestinationIsOutside($this->directory . '/root-sibling/app.jsonl');
            self::assertDirectoryExists($publicRoot, 'the boundary accepted a prefix-sharing sibling');
        } finally {
            rmdir($publicRoot);
        }
    }

    public function testAnExistingOwnerOnlyFileIsAccepted(): void
    {
        $file = $this->directory . '/app.jsonl';
        self::assertTrue(touch($file));
        self::assertTrue(chmod($file, 0o600));

        try {
            self::assertSame($file, JsonLogDestination::resolve($file));
        } finally {
            unlink($file);
        }
    }

    public function testAnOtherExecuteOnlyBitIsRefused(): void
    {
        $file = $this->directory . '/other-execute.jsonl';
        self::assertTrue(touch($file));
        self::assertTrue(chmod($file, 0o601));

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessageIsOrContains(
                'The existing JSON log file must be writable with owner-only permissions.',
            );
            JsonLogDestination::resolve($file);
        } finally {
            unlink($file);
        }
    }

    public function testASymlinkToAnExistingFileIsRefusedByThePrivacyGuard(): void
    {
        $target = $this->directory . '/target.jsonl';
        $link = $this->directory . '/link.jsonl';
        self::assertTrue(touch($target));
        self::assertTrue(chmod($target, 0o600));
        self::assertTrue(symlink($target, $link));

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessageIsOrContains('The JSON log destination must be a regular non-symlink file.');
            JsonLogFilePrivacy::assertExistingFileIsPrivate($link);
        } finally {
            unlink($link);
            unlink($target);
        }
    }

    public function testARuntimeWithoutADocumentRootLeavesTheBoundaryOpen(): void
    {
        unset($_SERVER['DOCUMENT_ROOT']);

        $resolved = JsonLogDestination::resolve(
            getcwd() . '/zef-logdst-boundary-' . bin2hex(random_bytes(4)) . '.jsonl',
        );

        self::assertStringEndsWith('.jsonl', $resolved);
    }
}
