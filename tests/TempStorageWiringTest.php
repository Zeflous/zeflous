<?php

declare(strict_types=1);

namespace Zef\Test;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Temp\FileTempStore;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Storage\StorageLane;
use Zef\Framework\TempStorageWiring;
use Zef\Test\Support\Adapters\Temp\TempTemporaryTree;

/**
 * Pins the temp family's wiring onto the `files/**` route map:
 * the writer's precedence matrix (the armed retention over the
 * derived `temp` lane, an explicitly spelled path verbatim and
 * never walking the tree, the combination refusal when the writer
 * is armed with neither), the walk's own fail-closed verdict
 * naming the lane, the retention declaration flowing into the
 * sweep's own bound -- and the composition seam's three boot
 * pins: the service identity resolving over a real boot, the
 * named halt the silent knob answers at the host's own ask, and
 * the warm boot staying green on the silent knob (the transient
 * registration's own posture -- the warmer never resolves the
 * family).
 *
 * @internal
 */
#[CoversClass(TempStorageWiring::class)]
final class TempStorageWiringTest extends TestCase
{
    private string $root;

    private string $bootParent = '';

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zef-temp-storage-wiring-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0o700);

        foreach (StorageLane::cases() as $lane) {
            mkdir($this->root . '/' . $lane->spelling(), 0o700);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        TempTemporaryTree::remove($this->root);

        if ($this->bootParent === '') {
            return;
        }

        TempTemporaryTree::remove($this->bootParent);
    }

    public function testASilentDeclarationRefusesWithTheWayOutSpelled(): void
    {
        try {
            TempStorageWiring::store(new Config([
                'temp' => ['ttl' => null],
            ]));
            self::fail('Resolving the writer on a silent declaration must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The temp.store writer is not declared: spell how long the'
                . ' lane keeps its files (temp.ttl) or compose the writer'
                . ' by hand over the layout address.',
                $configException->getMessage(),
            );
        }
    }

    public function testAnArmedDeclarationComposesTheWriterOverTheDerivedTempLane(): void
    {
        $store = TempStorageWiring::store(new Config([
            'temp' => ['ttl' => 3600],
            'storage' => ['root' => $this->root],
        ]));

        $path = $store->write('scratch bytes');

        self::assertSame(
            realpath($this->root . '/temp'),
            \dirname($path),
            'the derived address is the temp lane the root provisions',
        );
        self::assertFileExists($path);
        self::assertSame('scratch bytes', file_get_contents($path));
    }

    public function testAnExplicitPathAnswersVerbatimAndNeverWalksTheDeclaredRoot(): void
    {
        $explicit = $this->root . '/an-explicit-temp-store';
        mkdir($explicit, 0o700);

        $store = TempStorageWiring::store(new Config([
            'temp' => ['ttl' => 3600, 'path' => $explicit],
            'storage' => ['root' => $this->root . '/a-root-that-was-never-provisioned'],
        ]));

        $path = $store->write('b');

        self::assertSame(
            realpath($explicit),
            \dirname($path),
            "the explicit address is the deployment's own word, judged by the writer's own posture",
        );
    }

    public function testAnArmedWriterWithNeitherAnAddressNorARootRefuses(): void
    {
        try {
            TempStorageWiring::store(new Config([
                'temp' => ['ttl' => 3600],
            ]));
            self::fail('An armed writer without a directory to compose over must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The temp.store writer needs a directory: declare'
                . ' storage.root or spell temp.path.',
                $configException->getMessage(),
            );
        }
    }

    public function testADeclaredRootWhoseTempLaneIsMissingRefusesWithTheLaneNamed(): void
    {
        rmdir($this->root . '/temp');

        try {
            TempStorageWiring::store(new Config([
                'temp' => ['ttl' => 3600],
                'storage' => ['root' => $this->root],
            ]));
            self::fail('A declared root whose temp lane is missing must refuse the whole walk.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The storage lane 'temp' must be an existing writable non-symlink directory.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheRetentionDeclarationFlowsIntoTheSweepsOwnBound(): void
    {
        $stale = $this->root . '/temp/stale-scratch';
        file_put_contents($stale, 'stale');
        touch($stale, time() - 7200);

        $store = TempStorageWiring::store(new Config([
            'temp' => ['ttl' => 3600],
            'storage' => ['root' => $this->root],
        ]));

        $path = $store->write('c');

        self::assertFileExists($path);
        self::assertFileDoesNotExist(
            $stale,
            'the declared retention answers the sweep the riding write pays',
        );
    }

    public function testTheBootResolvesTheWritersServiceOverTheTempLane(): void
    {
        $bootTree = $this->temporaryBootTree([
            'temp.php' => '<?php return ["ttl" => 3600];',
            'storage.php' => '<?php return ["root" => ' . var_export($this->root, true) . '];',
        ]);

        $kernel = new ApplicationBootstrapper()->boot($bootTree . '/configs');

        $store = $kernel->container()->get('temp.store');

        self::assertInstanceOf(FileTempStore::class, $store);

        $path = $store->write('c');

        self::assertFileExists($path);

        self::assertNotSame(
            $store,
            $kernel->container()->get('temp.store'),
            'the transient registration answers a fresh writer every resolution',
        );
    }

    public function testTheBootRefusesTheServiceOnTheSilentKnob(): void
    {
        $bootTree = $this->temporaryBootTree([]);

        $kernel = new ApplicationBootstrapper()->boot($bootTree . '/configs');

        try {
            $kernel->container()->get('temp.store');
            self::fail('Resolving the writer without declaring it must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The temp.store writer is not declared: spell how long the'
                . ' lane keeps its files (temp.ttl) or compose the writer'
                . ' by hand over the layout address.',
                $configException->getMessage(),
            );
        }
    }

    public function testAWarmBootStaysGreenOnTheSilentKnob(): void
    {
        $bootTree = $this->temporaryBootTree([
            'aot.php' => '<?php return ["enabled" => false, "warm" => true];',
            // The default profile arms the CSRF and auth families without
            // secrets or tokens of their own -- a host opting into the
            // warm boot spells them or disarms the families; this pin is
            // about the temp family's own posture, so both are disarmed.
            'security.php' => '<?php return ["csrf" => ["enabled" => false],'
                . ' "auth" => ["enabled" => false]];',
        ]);

        $kernel = new ApplicationBootstrapper()->boot($bootTree . '/configs');

        self::assertTrue(
            $kernel->container()->has('temp.store'),
            'the warm boot answers the registration without ever resolving the family',
        );
    }

    /**
     * Lays a boot environment down under the temporary root: a
     * configuration directory (one file per entry) beside the
     * already-provisioned storage tree the root names.
     *
     * @param array<string, string> $files the configuration files to write,
     *                                     keyed by basename
     *
     * @return non-empty-string the parent directory holding the pair
     */
    private function temporaryBootTree(array $files): string
    {
        $parent = $this->root . '-boot-' . bin2hex(random_bytes(4));
        $configs = $parent . '/configs';

        mkdir($configs, 0o700, true);

        foreach ($files as $basename => $content) {
            file_put_contents($configs . '/' . $basename, $content);
        }

        $this->bootParent = $parent;

        return $parent;
    }
}
