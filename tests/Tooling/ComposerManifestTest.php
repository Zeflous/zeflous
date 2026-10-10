<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\ComposerManifest;
use Zef\Framework\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(ComposerManifest::class)]
final class ComposerManifestTest extends TestCase
{
    private string $composerJsonPath;

    private string $composerLockPath;

    #[Override]
    protected function setUp(): void
    {
        $this->composerJsonPath = (string) tempnam(sys_get_temp_dir(), 'zef-json-');
        $this->composerLockPath = (string) tempnam(sys_get_temp_dir(), 'zef-lock-');
    }

    #[Override]
    protected function tearDown(): void
    {
        unlink($this->composerJsonPath);
        unlink($this->composerLockPath);
    }

    public function testReadsTheRequireSectionAndTheProductionPackages(): void
    {
        $manifest = $this->manifest(
            '{"require":{"php":"^8.4"},"require-dev":{"phpunit/phpunit":"^13.4"}}',
            '{"packages":[],"packages-dev":[{"name":"phpstan/phpstan"}]}',
        );

        self::assertSame(['php' => '^8.4'], $manifest->require);
        self::assertSame([], $manifest->packages);
    }

    public function testTreatsAnAbsentRequireSectionAsNoDependencies(): void
    {
        $manifest = $this->manifest('{"autoload":{"files":["src/autoload.php"]}}', '{"packages":[]}');

        self::assertSame([], $manifest->require);
    }

    public function testTreatsAnAbsentPackagesKeyAsNoProductionPackages(): void
    {
        $manifest = $this->manifest('{"require":{"php":"^8.4"}}', '{"packages-dev":[]}');

        self::assertSame([], $manifest->packages);
    }

    public function testThrowsWhenComposerJsonIsMissing(): void
    {
        try {
            ComposerManifest::fromFiles('/nonexistent/composer.json', $this->composerLockPath);
            self::fail('A missing composer.json must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'composer.json not found: /nonexistent/composer.json',
                $toolingException->getMessage(),
            );
        }
    }

    public function testThrowsWhenComposerLockIsMissing(): void
    {
        file_put_contents($this->composerJsonPath, '{"require":{}}');

        try {
            ComposerManifest::fromFiles($this->composerJsonPath, '/nonexistent/composer.lock');
            self::fail('A missing composer.lock must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'composer.lock not found: /nonexistent/composer.lock',
                $toolingException->getMessage(),
            );
        }
    }

    public function testThrowsWhenComposerJsonIsNotAJsonObject(): void
    {
        file_put_contents($this->composerJsonPath, 'this is not json');

        try {
            ComposerManifest::fromFiles($this->composerJsonPath, $this->composerLockPath);
            self::fail('A malformed composer.json must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'composer.json does not contain a JSON object: ' . $this->composerJsonPath,
                $toolingException->getMessage(),
            );
        }
    }

    public function testThrowsWhenTheManifestIsAScalar(): void
    {
        file_put_contents($this->composerJsonPath, '"php"');

        try {
            ComposerManifest::fromFiles($this->composerJsonPath, $this->composerLockPath);
            self::fail('A scalar manifest must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'composer.json does not contain a JSON object: ' . $this->composerJsonPath,
                $toolingException->getMessage(),
            );
        }
    }

    public function testTreatsAnEmptyJsonArrayAsAnEmptyManifest(): void
    {
        // '[]' decodes to an empty array, indistinguishable from '{}'; it
        // carries no keys, so it is accepted as an empty manifest (and the
        // gate then fails on the exact-match check instead).
        $manifest = $this->manifest('[]', '[]');

        self::assertSame([], $manifest->require);
        self::assertSame([], $manifest->packages);
    }

    public function testThrowsWhenComposerLockIsNotAJsonObject(): void
    {
        file_put_contents($this->composerJsonPath, '{"require":{}}');
        file_put_contents($this->composerLockPath, '[1,2,3]');

        try {
            ComposerManifest::fromFiles($this->composerJsonPath, $this->composerLockPath);
            self::fail('A non-object composer.lock must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'composer.lock does not contain a JSON object: ' . $this->composerLockPath,
                $toolingException->getMessage(),
            );
        }
    }

    public function testThrowsWhenTheRequireSectionIsMalformed(): void
    {
        try {
            $this->manifest('{"require":"php"}', '{"packages":[]}');
            self::fail('A malformed require section must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'composer.json require section is malformed: it must be an object.',
                $toolingException->getMessage(),
            );
        }
    }

    public function testThrowsWhenThePackagesSectionIsMalformed(): void
    {
        try {
            $this->manifest('{"require":{}}', '{"packages":"phpunit"}');
            self::fail('A malformed packages section must raise a ToolingException.');
        } catch (ToolingException $toolingException) {
            self::assertSame(
                'composer.lock packages section is malformed: it must be an array.',
                $toolingException->getMessage(),
            );
        }
    }

    public function testKeepsEveryTopLevelSectionOfTheManifest(): void
    {
        // A manifest whose interesting sections are NOT the first key: a
        // regression that truncates the decoded manifest to a single entry
        // (for example a mutated array slice) must not silently drop the
        // sections the gate reads.
        $manifest = $this->manifest(
            '{"name":"zeflous/zeflous","require":{"php":"^8.4"}}',
            '{"content-hash":"abc123","packages":[{"name":"psr/log"}]}',
        );

        self::assertSame(['php' => '^8.4'], $manifest->require);
        self::assertSame([['name' => 'psr/log']], $manifest->packages);
    }

    private function manifest(string $composerJson, string $composerLock): ComposerManifest
    {
        file_put_contents($this->composerJsonPath, $composerJson);
        file_put_contents($this->composerLockPath, $composerLock);

        return ComposerManifest::fromFiles($this->composerJsonPath, $this->composerLockPath);
    }
}
