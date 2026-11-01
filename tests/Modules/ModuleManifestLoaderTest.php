<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\ModuleManifestLoader;
use Zef\Framework\Modules\ModuleException;

/**
 * The module side's file loader: a `module.php` or a schema file must
 * exist, must load, and must return an array -- every other answer is a
 * refusal naming the path and the subject, the configuration layer's own
 * file-loader posture at the module lane's scale.
 *
 * @internal
 */
#[CoversClass(ModuleManifestLoader::class)]
#[CoversClass(ModuleException::class)]
final class ModuleManifestLoaderTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/fixtures';

    public function testAnArrayPayloadLoadsVerbatim(): void
    {
        $payload = new ModuleManifestLoader()->load(
            self::FIXTURES . '/alpha-manifest/module.php',
            'module manifest',
        );

        self::assertSame('test/alpha', $payload['name']);
    }

    public function testAMissingPathIsRefusedNamingIt(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIs(
            "The module manifest '" . self::FIXTURES . "/nowhere/module.php' does not exist "
            . '(a module-side path is spelled absolute, rooted at its own directory).',
        );

        new ModuleManifestLoader()->load(self::FIXTURES . '/nowhere/module.php', 'module manifest');
    }

    public function testADirectoryPathIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('does not exist');

        new ModuleManifestLoader()->load(self::FIXTURES . '/alpha-manifest', 'module manifest');
    }

    public function testAFileThatThrowsIsRefusedWithTheReasonChained(): void
    {
        try {
            new ModuleManifestLoader()->load(self::FIXTURES . '/throwing/module.php', 'module manifest');

            self::fail('the throwing fixture file was loaded');
        } catch (ModuleException $moduleException) {
            self::assertSame(
                "The module manifest '" . self::FIXTURES
                . "/throwing/module.php' failed to load: the fixture file refuses to load.",
                $moduleException->getMessage(),
            );
            self::assertSame(0, $moduleException->getCode());
            self::assertSame(
                'the fixture file refuses to load',
                $moduleException->getPrevious()?->getMessage(),
            );
        }
    }

    public function testANonArrayPayloadIsRefusedWithItsType(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module manifest '" . self::FIXTURES . "/broken/module.php' must return an array; string arrived.",
        );

        new ModuleManifestLoader()->load(self::FIXTURES . '/broken/module.php', 'module manifest');
    }

    public function testASchemaSubjectNamesItselfInTheRefusals(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module schema '" . self::FIXTURES . "/nowhere/schema.php' does not exist",
        );

        new ModuleManifestLoader()->load(self::FIXTURES . '/nowhere/schema.php', 'module schema');
    }
}
