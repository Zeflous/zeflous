<?php

declare(strict_types=1);

namespace Zef\Test;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\CacheStorageWiring;
use Zef\Framework\Config\Cache\ConfigCache;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Storage\StorageLane;

/**
 * Pins the compiled caches' wiring onto the `files/**` route map: the
 * precedence matrix (an explicitly handed instance answers verbatim
 * and never touches the tree, a declared root derives the `cache`
 * lane's canonical artifact names, a null or empty root answers the
 * no-cache posture), and the walk's own refusal -- a declared root
 * whose `cache/` lane is missing names the lane and refuses.
 *
 * @internal
 */
#[CoversClass(CacheStorageWiring::class)]
final class CacheStorageWiringTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zef-cache-storage-wiring-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0o700);

        foreach (StorageLane::cases() as $lane) {
            mkdir($this->root . '/' . $lane->spelling(), 0o700);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        foreach (StorageLane::cases() as $lane) {
            $artifacts = glob($this->root . '/' . $lane->spelling() . '/*');

            if (!\is_array($artifacts)) {
                continue;
            }

            foreach ($artifacts as $artifact) {
                if (!is_file($artifact)) {
                    continue;
                }

                unlink($artifact);
            }
        }

        $entries = glob($this->root . '/*');
        foreach (\is_array($entries) ? $entries : [] as $entry) {
            if (is_dir($entry)) {
                rmdir($entry);
            } elseif (is_file($entry)) {
                unlink($entry);
            }
        }

        rmdir($this->root);
    }

    public function testAnExplicitConfigurationCacheAnswersVerbatimAndNeverWalksTheTree(): void
    {
        $configCache = new ConfigCache($this->root . '/elsewhere.php');

        self::assertSame(
            $configCache,
            CacheStorageWiring::configCache($configCache, $this->root . '/never-created'),
            'an explicitly handed instance answers verbatim, the unusable root never walked',
        );
    }

    public function testADeclaredRootDerivesTheConfigurationCacheFromTheCacheLane(): void
    {
        $cache = CacheStorageWiring::configCache(null, $this->root);

        self::assertInstanceOf(ConfigCache::class, $cache);
        self::assertFalse($cache->exists(), 'a derived cache addresses an artifact the boot has not written yet');

        $cache->dump(['answer' => 42]);

        $expected = realpath($this->root . '/' . StorageLane::Cache->spelling())
            . \DIRECTORY_SEPARATOR . 'zef-config.php';

        self::assertFileExists($expected, "the artifact lands at the cache lane's canonical name");
        self::assertSame(['answer' => 42], $cache->load(), 'the derived address round-trips its own payload');
    }

    public function testADeclaredRootDerivesTheContainerAotCacheFromTheCacheLane(): void
    {
        $cache = CacheStorageWiring::containerAotCache(null, $this->root);

        self::assertInstanceOf(ContainerAotCache::class, $cache);

        $expected = realpath($this->root . '/' . StorageLane::Cache->spelling())
            . \DIRECTORY_SEPARATOR . 'zef-container-aot.php';

        self::assertSame($expected, $cache->file(), "the derived artifact address is the cache lane's canonical name");
    }

    public function testAnExplicitContainerAotCacheAnswersVerbatimAndNeverWalksTheTree(): void
    {
        $containerAotCache = new ContainerAotCache($this->root . '/elsewhere.php');

        self::assertSame(
            $containerAotCache,
            CacheStorageWiring::containerAotCache($containerAotCache, $this->root . '/never-created'),
            'an explicitly handed instance answers verbatim, the unusable root never walked',
        );
    }

    public function testANullRootAnswersTheNoCachePostureForBothSeams(): void
    {
        self::assertNull(
            CacheStorageWiring::configCache(null, null),
            'a null root declares no layout: the configuration seam stays unset',
        );
        self::assertNull(
            CacheStorageWiring::containerAotCache(null, null),
            'a null root declares no layout: the AOT seam stays unset',
        );
    }

    public function testAnEmptyRootDeclaresNoLayoutForBothSeams(): void
    {
        self::assertNull(CacheStorageWiring::configCache(null, ''));
        self::assertNull(CacheStorageWiring::containerAotCache(null, ''));
    }

    public function testAMissingCacheLaneRefusesTheWalkWithTheLaneNamed(): void
    {
        rmdir($this->root . '/' . StorageLane::Cache->spelling());

        try {
            CacheStorageWiring::configCache(null, $this->root);
            self::fail('A declared root with a missing cache lane must refuse the derivation.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The storage lane 'cache' must be an existing writable non-symlink directory.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAMissingCacheLaneRefusesTheAotDerivationWithTheLaneNamed(): void
    {
        rmdir($this->root . '/' . StorageLane::Cache->spelling());

        try {
            CacheStorageWiring::containerAotCache(null, $this->root);
            self::fail('A declared root with a missing cache lane must refuse the AOT derivation.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The storage lane 'cache' must be an existing writable non-symlink directory.",
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
