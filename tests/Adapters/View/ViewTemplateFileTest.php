<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\View;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\View\ViewTemplateFile;
use Zef\Framework\View\ViewException;
use Zef\Framework\View\ViewName;
use Zef\Test\Support\Adapters\View\ViewFileDoubleState;

/**
 * Pins the resolution fold's two walls: the miss that names the
 * NAME alone (never the resolved address the lane's privacy posture
 * keeps), and the containment that refuses the symlink escape while
 * answering the inside symlink as the deployment's own alias -- plus
 * the extension honesty (a template is a `.php` file or it is a
 * miss) and the unresolvable-template arm the armed double spells.
 *
 * @internal
 */
#[CoversClass(ViewTemplateFile::class)]
final class ViewTemplateFileTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zef-view-file-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0o700);
        mkdir($this->root . '/users', 0o700);

        file_put_contents($this->root . '/about.php', 'about');
        file_put_contents($this->root . '/plain.txt', 'not a template');
        file_put_contents($this->root . '/users/profile.php', 'profile');
    }

    #[Override]
    protected function tearDown(): void
    {
        ViewFileDoubleState::reset();

        if (is_link($this->root . '/alias.php')) {
            unlink($this->root . '/alias.php');
        }

        if (is_link($this->root . '/escape.php')) {
            unlink($this->root . '/escape.php');
        }

        $this->removeTree($this->root);
    }

    public function testResolvesATemplateUnderTheRoot(): void
    {
        self::assertSame(
            realpath($this->root . '/about.php'),
            ViewTemplateFile::resolve($this->root, new ViewName('about')),
        );
    }

    public function testResolvesANestedTemplateUnderTheRoot(): void
    {
        self::assertSame(
            realpath($this->root . '/users/profile.php'),
            ViewTemplateFile::resolve($this->root, new ViewName('users/profile')),
        );
    }

    public function testAMissNamesTheNameAlone(): void
    {
        try {
            ViewTemplateFile::resolve($this->root, new ViewName('missing'));
            self::fail('The miss was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "The view template 'missing' does not answer under the templates root.",
                $viewException->getMessage(),
            );
            self::assertStringNotContainsString(
                $this->root,
                $viewException->getMessage(),
                'A miss must never disclose the resolved address.',
            );
        }
    }

    public function testANameWithoutTheNativeExtensionStillMisses(): void
    {
        try {
            ViewTemplateFile::resolve($this->root, new ViewName('plain'));
            self::fail('The extension-less resolution did not refuse.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "The view template 'plain' does not answer under the templates root.",
                $viewException->getMessage(),
            );
        }
    }

    public function testAnUnreadableTemplateIsAMiss(): void
    {
        chmod($this->root . '/about.php', 0o200);

        try {
            ViewTemplateFile::resolve($this->root, new ViewName('about'));
            self::fail('The unreadable template was not refused as a miss.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "The view template 'about' does not answer under the templates root.",
                $viewException->getMessage(),
            );
        } finally {
            chmod($this->root . '/about.php', 0o600);
        }
    }

    public function testADirectoryUnderTheNameIsAMiss(): void
    {
        try {
            ViewTemplateFile::resolve($this->root, new ViewName('users'));
            self::fail('The directory-named resolution did not refuse.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "The view template 'users' does not answer under the templates root.",
                $viewException->getMessage(),
            );
        }
    }

    public function testASymlinkEscapingTheRootIsRefused(): void
    {
        $outsideTarget = sys_get_temp_dir() . '/zef-view-outside-' . bin2hex(random_bytes(8)) . '.php';
        file_put_contents($outsideTarget, 'stolen');
        symlink($outsideTarget, $this->root . '/escape.php');

        try {
            ViewTemplateFile::resolve($this->root, new ViewName('escape'));
            self::fail('The escaping symlink was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "The view template 'escape' escapes the templates root.",
                $viewException->getMessage(),
            );
        } finally {
            unlink($outsideTarget);
        }
    }

    public function testAnInsideSymlinkIsTheDeploymentsOwnAlias(): void
    {
        symlink($this->root . '/about.php', $this->root . '/alias.php');

        self::assertSame(
            realpath($this->root . '/about.php'),
            ViewTemplateFile::resolve($this->root, new ViewName('alias')),
        );
    }

    public function testAnUnresolvableTemplateIsRefused(): void
    {
        ViewFileDoubleState::$realpathAnswer = false;

        try {
            ViewTemplateFile::resolve($this->root, new ViewName('about'));
            self::fail('The unresolvable template was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "The view template 'about' escapes the templates root.",
                $viewException->getMessage(),
            );
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

            if (is_dir($entry) && !is_link($entry)) {
                $this->removeTree($entry);
            } else {
                unlink($entry);
            }
        }

        rmdir($directory);
    }
}
