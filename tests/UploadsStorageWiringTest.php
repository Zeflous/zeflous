<?php

declare(strict_types=1);

namespace Zef\Test;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Uploads\FileUploadsStore;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Exception\UploadTooLargeException;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\UploadedFile;
use Zef\Framework\Storage\StorageLane;
use Zef\Framework\UploadsStorageWiring;

/**
 * Pins the uploads family's wiring onto the `files/**` route map:
 * the writer's precedence matrix (the armed allowlist over the
 * derived `uploads` lane, an explicitly spelled path verbatim and
 * never walking the tree, the combination refusal when the writer
 * is armed with neither), the walk's own fail-closed verdict naming
 * the lane, the declaration's ceiling and allowlist flowing into
 * the writer's own bounds -- and the composition seam's three boot
 * pins: the service identity resolving over a real boot, the named
 * halt the silent knob answers at the host's own ask, and the warm
 * boot staying green on the silent knob (the transient
 * registration's own posture -- the warmer never resolves the
 * family).
 *
 * @internal
 */
#[CoversClass(UploadsStorageWiring::class)]
final class UploadsStorageWiringTest extends TestCase
{
    private string $root;

    private string $bootParent = '';

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zef-uploads-storage-wiring-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0o700);

        foreach (StorageLane::cases() as $lane) {
            mkdir($this->root . '/' . $lane->spelling(), 0o700);
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTree($this->root);

        if ($this->bootParent === '') {
            return;
        }

        $this->removeTree($this->bootParent);
    }

    public function testASilentDeclarationRefusesWithTheWayOutSpelled(): void
    {
        try {
            UploadsStorageWiring::store(new Config([
                'uploads' => ['types' => []],
            ]));
            self::fail('Resolving the writer on a silent declaration must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The uploads.store writer is not declared: spell the media'
                . ' types the lane accepts (uploads.types) or compose the'
                . ' writer by hand over the layout address.',
                $configException->getMessage(),
            );
        }
    }

    public function testAnArmedDeclarationComposesTheWriterOverTheDerivedUploadsLane(): void
    {
        $store = UploadsStorageWiring::store(new Config([
            'uploads' => ['types' => ['image/png']],
            'storage' => ['root' => $this->root],
        ]));

        $path = $store->store(
            new UploadedFile(
                Stream::fromString('png bytes'),
                9,
                \UPLOAD_ERR_OK,
                'a.png',
                'image/png',
            ),
        );

        self::assertSame(
            realpath($this->root . '/uploads'),
            \dirname($path),
            'the derived address is the uploads lane the root provisions',
        );
        self::assertFileExists($path);
        self::assertSame('png bytes', file_get_contents($path));
    }

    public function testAnExplicitPathAnswersVerbatimAndNeverWalksTheDeclaredRoot(): void
    {
        $explicit = $this->root . '/an-explicit-uploads-store';
        mkdir($explicit, 0o700);

        $store = UploadsStorageWiring::store(new Config([
            'uploads' => ['types' => ['image/png'], 'path' => $explicit],
            'storage' => ['root' => $this->root . '/a-root-that-was-never-provisioned'],
        ]));

        $path = $store->store(
            new UploadedFile(
                Stream::fromString('b'),
                1,
                \UPLOAD_ERR_OK,
                'b.png',
                'image/png',
            ),
        );

        self::assertSame(
            realpath($explicit),
            \dirname($path),
            "the explicit address is the deployment's own word, judged by the writer's own posture",
        );
    }

    public function testAnArmedWriterWithNeitherAnAddressNorARootRefuses(): void
    {
        try {
            UploadsStorageWiring::store(new Config([
                'uploads' => ['types' => ['image/png']],
            ]));
            self::fail('An armed writer without a directory to compose over must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The uploads.store writer needs a directory: declare'
                . ' storage.root or spell uploads.path.',
                $configException->getMessage(),
            );
        }
    }

    public function testADeclaredRootWhoseUploadsLaneIsMissingRefusesWithTheLaneNamed(): void
    {
        rmdir($this->root . '/uploads');

        try {
            UploadsStorageWiring::store(new Config([
                'uploads' => ['types' => ['image/png']],
                'storage' => ['root' => $this->root],
            ]));
            self::fail('A declared root whose uploads lane is missing must refuse the whole walk.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The storage lane 'uploads' must be an existing writable non-symlink directory.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheCeilingDeclarationFlowsIntoTheWritersOwnBound(): void
    {
        $store = UploadsStorageWiring::store(new Config([
            'uploads' => ['types' => ['image/png'], 'max_bytes' => 2],
            'storage' => ['root' => $this->root],
        ]));

        try {
            $store->store(
                new UploadedFile(
                    Stream::fromString('abc'),
                    3,
                    \UPLOAD_ERR_OK,
                    'a.png',
                    'image/png',
                ),
            );
            self::fail('An upload past the declared ceiling must refuse.');
        } catch (UploadTooLargeException $uploadTooLargeException) {
            self::assertSame(3, $uploadTooLargeException->size);
            self::assertSame(2, $uploadTooLargeException->maxBytes);
        }
    }

    public function testTheBootResolvesTheWritersServiceOverTheUploadsLane(): void
    {
        $bootTree = $this->temporaryBootTree([
            'uploads.php' => '<?php return ["types" => ["image/png"]];',
            'storage.php' => '<?php return ["root" => ' . var_export($this->root, true) . '];',
        ]);

        $kernel = new ApplicationBootstrapper()->boot($bootTree . '/configs');

        $store = $kernel->container()->get('uploads.store');

        self::assertInstanceOf(FileUploadsStore::class, $store);

        $path = $store->store(
            new UploadedFile(
                Stream::fromString('c'),
                1,
                \UPLOAD_ERR_OK,
                'c.png',
                'image/png',
            ),
        );

        self::assertFileExists($path);

        self::assertNotSame(
            $store,
            $kernel->container()->get('uploads.store'),
            'the transient registration answers a fresh writer every resolution',
        );
    }

    public function testTheBootRefusesTheServiceOnTheSilentKnob(): void
    {
        $bootTree = $this->temporaryBootTree([]);

        $kernel = new ApplicationBootstrapper()->boot($bootTree . '/configs');

        try {
            $kernel->container()->get('uploads.store');
            self::fail('Resolving the writer without declaring it must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The uploads.store writer is not declared: spell the media'
                . ' types the lane accepts (uploads.types) or compose the'
                . ' writer by hand over the layout address.',
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
            // about the uploads family's own posture, so both are disarmed.
            'security.php' => '<?php return ["csrf" => ["enabled" => false],'
                . ' "auth" => ["enabled" => false]];',
        ]);

        $kernel = new ApplicationBootstrapper()->boot($bootTree . '/configs');

        self::assertTrue(
            $kernel->container()->has('uploads.store'),
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
