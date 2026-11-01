<?php

declare(strict_types=1);

namespace Zef\Test\Modules;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\ModuleConstructions;
use Zef\Framework\ModuleManifestLoader;
use Zef\Framework\ModuleResolver;
use Zef\Framework\Modules\ModuleBootRefusals;
use Zef\Framework\Modules\ModuleDeclaration;
use Zef\Framework\Modules\ModuleException;
use Zef\Framework\Modules\ModuleExceptionSpelling;
use Zef\Framework\Modules\ModuleManifest;
use Zef\Framework\Modules\ModuleMetadata;
use Zef\Framework\Modules\ModuleName;
use Zef\Framework\Modules\ModuleRequires;
use Zef\Framework\Modules\ModuleVersion;
use Zef\Test\Modules\Fixtures\AlphaModule;
use Zef\Test\Modules\Fixtures\ExplodingModule;
use Zef\Test\Modules\Fixtures\NotModule;

/**
 * The discovery fold: both entry spellings converge on one answer -- the
 * class spelling through the autoloader, the manifest spelling through the
 * loaded payload -- and every dishonesty on the way is a refusal naming
 * the subject: a missing class, a class without the contract, a
 * constructor that cannot answer, a missing or malformed manifest, a
 * class whose identity disagrees with its manifest, and the relative
 * schema spelling resolved against the manifest's own directory.
 *
 * @internal
 */
#[CoversClass(ModuleResolver::class)]
#[CoversClass(ModuleConstructions::class)]
#[CoversClass(ModuleBootRefusals::class)]
#[CoversClass(ModuleManifestLoader::class)]
#[CoversClass(ModuleManifest::class)]
#[CoversClass(ModuleDeclaration::class)]
#[CoversClass(ModuleMetadata::class)]
#[CoversClass(ModuleName::class)]
#[CoversClass(ModuleVersion::class)]
#[CoversClass(ModuleRequires::class)]
#[CoversClass(ModuleExceptionSpelling::class)]
#[CoversClass(ModuleException::class)]
final class ModuleResolverTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/fixtures';

    private ModuleResolver $moduleResolver;

    #[Override]
    protected function setUp(): void
    {
        $this->moduleResolver = new ModuleResolver();
    }

    public function testTheClassSpellingResolvesThroughTheAutoloader(): void
    {
        [$declaration, $module] = $this->moduleResolver->resolve(AlphaModule::class);

        self::assertSame(AlphaModule::class, $declaration->class);
        self::assertSame('test/alpha', $declaration->metadata->name);
        self::assertNull($declaration->configSchema);
        self::assertInstanceOf(AlphaModule::class, $module);
    }

    public function testAnUnknownClassIsRefusedNamingIt(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module class 'Zef\\Test\\Modules\\Fixtures\\MissingClass' does not exist",
        );

        $this->moduleResolver->resolve('Zef\Test\Modules\Fixtures\MissingClass');
    }

    public function testAClassWithoutTheContractIsRefused(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('does not implement the module contract');

        $this->moduleResolver->resolve(NotModule::class);
    }

    public function testAConstructorThatThrowsIsRefusedWithTheReason(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module class '" . ExplodingModule::class . "' could not be constructed: "
            . 'the fixture module refuses to exist.',
        );

        $this->moduleResolver->resolve(ExplodingModule::class);
    }

    public function testTheManifestSpellingParsesAndAgrees(): void
    {
        [$declaration, $module] = $this->moduleResolver->resolve(self::FIXTURES . '/alpha-manifest/module.php');

        self::assertSame(AlphaModule::class, $declaration->class);
        self::assertSame('test/alpha', $declaration->metadata->name);
        self::assertNull($declaration->configSchema);
        self::assertInstanceOf(AlphaModule::class, $module);
    }

    public function testTheRelativeSchemaSpellingResolvesAgainstTheManifestDirectory(): void
    {
        [$declaration] = $this->moduleResolver->resolve(self::FIXTURES . '/with-schema/module.php');

        self::assertSame(
            self::FIXTURES . '/with-schema/config/schema.php',
            $declaration->configSchema,
        );
    }

    public function testARootedSchemaSpellingRidesVerbatim(): void
    {
        $rooted = sys_get_temp_dir() . '/zef-module-rooted-' . uniqid('', false) . '.php';

        file_put_contents($rooted, '<?php return ' . var_export([
            'name' => 'test/echo',
            'version' => '1.0.0',
            'class' => Fixtures\EchoContextModule::class,
            'config_schema' => '/absolute/schema.php',
        ], true) . ';');

        try {
            [$declaration] = $this->moduleResolver->resolve($rooted);
        } finally {
            unlink($rooted);
        }

        self::assertSame('/absolute/schema.php', $declaration->configSchema);
    }

    public function testAListeningManifestAndClassIdentityIsRefusedWithBothSpellings(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "answers the identity 'test/other' at '2.0.0' requiring [] while its manifest '"
            . self::FIXTURES . "/mismatched/module.php' declares 'test/wrong' at '1.0.0'",
        );

        $this->moduleResolver->resolve(self::FIXTURES . '/mismatched/module.php');
    }

    public function testAManifestWhoseClassDoesNotExistIsRefusedNamingTheManifest(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains(
            "The module class 'Zef\\Test\\Modules\\Fixtures\\MissingModule' (declared by the manifest '"
            . self::FIXTURES . "/missing-class/module.php') does not exist",
        );

        $this->moduleResolver->resolve(self::FIXTURES . '/missing-class/module.php');
    }

    public function testAManifestThatIsNotAnArrayIsRefusedByTheLoader(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('must return an array; string arrived.');

        $this->moduleResolver->resolve(self::FIXTURES . '/broken/module.php');
    }

    public function testAManifestThatFailsItsFieldSchemaIsRefusedWithTheViolations(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessageIsOrContains('The module manifest carries violations');

        $this->moduleResolver->resolve(self::FIXTURES . '/badfields/module.php');
    }
}
