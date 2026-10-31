<?php

declare(strict_types=1);

namespace Zef\Test;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Event\FileDeliveryStore;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Event\DeferredDeliveryStore;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\ListenerIdentity;
use Zef\Framework\EventStorageWiring;
use Zef\Framework\Storage\StorageLane;

/**
 * Pins the events family's wiring onto the `files/**` route map:
 * the wired default's precedence matrix (the `file` word over the
 * derived `queue` lane, an explicitly spelled path verbatim and
 * never walking the tree, the combination refusal when the word is
 * spelled with neither), the walk's own fail-closed verdict naming
 * the lane, the capacity declaration flowing into the store's own
 * admission bound -- and the composition seam's three boot pins:
 * the service identity resolving over a real boot, the named halt
 * the silent knob answers at the host's own ask, and the warm boot
 * staying green on the silent knob (the transient registration's
 * own posture -- the warmer never resolves the family).
 *
 * @internal
 */
#[CoversClass(EventStorageWiring::class)]
final class EventStorageWiringTest extends TestCase
{
    private string $root;

    private string $bootParent = '';

    #[Override]
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/zef-event-storage-wiring-' . bin2hex(random_bytes(8));
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
            EventStorageWiring::store(new Config([
                'events' => ['deferred' => ['store' => '']],
            ]));
            self::fail('Resolving the wired default on a silent declaration must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The events.deferred.store wired default is not declared:'
                . " spell the 'file' word (events.deferred.store) or compose"
                . ' the durable arm over your own DeferredDeliveryStore.',
                $configException->getMessage(),
            );
        }
    }

    public function testTheFileWordComposesTheStoreOverTheDerivedQueueLane(): void
    {
        $store = EventStorageWiring::store(new Config([
            'events' => ['deferred' => ['store' => 'file']],
            'storage' => ['root' => $this->root],
        ]));

        self::assertInstanceOf(FileDeliveryStore::class, $store);

        $envelope = self::envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

        $store->save($envelope);

        self::assertSame(1, $store->pendingCount());
        self::assertFileExists(
            $this->root . '/queue/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json',
            'the derived address is the queue lane the root provisions',
        );

        $store->settle($envelope);

        self::assertSame(0, $store->pendingCount());
    }

    public function testAnExplicitPathAnswersVerbatimAndNeverWalksTheDeclaredRoot(): void
    {
        $explicit = $this->root . '/an-explicit-queue-store';
        mkdir($explicit, 0o700);

        $store = EventStorageWiring::store(new Config([
            'events' => ['deferred' => ['store' => 'file', 'path' => $explicit]],
            'storage' => ['root' => $this->root . '/a-root-that-was-never-provisioned'],
        ]));

        $store->save(self::envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'));

        self::assertSame(1, $store->pendingCount());
        self::assertFileExists(
            $explicit . '/bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.json',
            "the explicit address is the deployment's own word, judged by the store's own posture",
        );
    }

    public function testTheFileWordWithNeitherAnAddressNorARootRefuses(): void
    {
        try {
            EventStorageWiring::store(new Config([
                'events' => ['deferred' => ['store' => 'file']],
            ]));
            self::fail('The file word without a directory to compose over must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The events.deferred file store needs a directory: declare'
                . ' storage.root or spell events.deferred.path.',
                $configException->getMessage(),
            );
        }
    }

    public function testADeclaredRootWhoseQueueLaneIsMissingRefusesWithTheLaneNamed(): void
    {
        rmdir($this->root . '/queue');

        try {
            EventStorageWiring::store(new Config([
                'events' => ['deferred' => ['store' => 'file']],
                'storage' => ['root' => $this->root],
            ]));
            self::fail('A declared root whose queue lane is missing must refuse the whole walk.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The storage lane 'queue' must be an existing writable non-symlink directory.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheCapacityDeclarationFlowsIntoTheStoresAdmissionBound(): void
    {
        $store = EventStorageWiring::store(new Config([
            'events' => ['deferred' => ['store' => 'file', 'capacity' => 2]],
            'storage' => ['root' => $this->root],
        ]));

        try {
            $store->save(
                self::envelope('cccccccccccccccccccccccccccccccc'),
                self::envelope('dddddddddddddddddddddddddddddddd'),
                self::envelope('eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee'),
            );
            self::fail('A batch past the declared capacity must be refused whole.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store is full: capacity 2, occupied 0, demanded 3'
                . ' -- drain before deferring more.',
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(0, $store->pendingCount(), 'a refused batch leaves nothing behind');
    }

    public function testTheBootResolvesTheWiredDefaultServiceOverTheQueueLane(): void
    {
        $bootTree = $this->temporaryBootTree([
            'events.php' => '<?php return ["deferred" => ["store" => "file"]];',
            'storage.php' => '<?php return ["root" => ' . var_export($this->root, true) . '];',
        ]);

        $kernel = new ApplicationBootstrapper()->boot($bootTree . '/configs');

        $store = $kernel->container()->get('events.deferred.store');

        self::assertInstanceOf(DeferredDeliveryStore::class, $store);

        $store->save(self::envelope('ffffffffffffffffffffffffffffffff'));

        self::assertSame(1, $store->pendingCount());
        self::assertFileExists($this->root . '/queue/ffffffffffffffffffffffffffffffff.json');

        self::assertNotSame(
            $store,
            $kernel->container()->get('events.deferred.store'),
            'the transient registration answers a fresh store every resolution -- the books are the directory',
        );
    }

    public function testTheBootRefusesTheServiceOnTheSilentKnob(): void
    {
        $bootTree = $this->temporaryBootTree([]);

        $kernel = new ApplicationBootstrapper()->boot($bootTree . '/configs');

        try {
            $kernel->container()->get('events.deferred.store');
            self::fail('Resolving the wired default without declaring it must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The events.deferred.store wired default is not declared:'
                . " spell the 'file' word (events.deferred.store) or compose"
                . ' the durable arm over your own DeferredDeliveryStore.',
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
            // about the events family's own posture, so both are disarmed.
            'security.php' => '<?php return ["csrf" => ["enabled" => false],'
                . ' "auth" => ["enabled" => false]];',
        ]);

        $kernel = new ApplicationBootstrapper()->boot($bootTree . '/configs');

        self::assertTrue(
            $kernel->container()->has('events.deferred.store'),
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
        $parent = sys_get_temp_dir() . '/zef-event-storage-boot-' . bin2hex(random_bytes(8));
        mkdir($parent . '/configs', 0o700, true);

        $this->bootParent = $parent;

        foreach ($files as $name => $content) {
            file_put_contents($parent . '/configs/' . $name, $content);
        }

        return $parent;
    }

    private function envelope(string $entryId): DeliveryEnvelope
    {
        return new DeliveryEnvelope(
            new DeliveryEntryId($entryId),
            new EventName('order.paid'),
            ['amount' => 42],
            new ListenerIdentity('app.orders.audit'),
            1,
            0,
        );
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
