<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Session;

use InvalidArgumentException;
use Iterator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Adapters\Session\FileSessionAdapter;
use Zef\Framework\Adapters\Session\SessionBagDepth;
use Zef\Framework\Adapters\Session\SessionRecordDecoder;
use Zef\Framework\Adapters\Session\SessionRecordEncoder;
use Zef\Framework\Adapters\Session\SessionRecordExchange;
use Zef\Framework\Adapters\Session\SessionRecordFiles;
use Zef\Framework\Adapters\Session\SessionRecordLoad;
use Zef\Framework\Adapters\Session\SessionRecordLock;
use Zef\Framework\Adapters\Session\SessionRecordQuarantine;
use Zef\Framework\Adapters\Session\SessionRecordRetirement;
use Zef\Framework\Adapters\Session\SessionRecordScan;
use Zef\Framework\Adapters\Session\SessionRecordShape;
use Zef\Framework\Adapters\Session\SessionRecordStaging;
use Zef\Framework\Adapters\Session\SessionRecordValues;
use Zef\Framework\Adapters\Session\SessionStoreDirectory;
use Zef\Framework\Adapters\Session\SessionSweep;
use Zef\Framework\Adapters\Session\SessionSweepCadence;
use Zef\Test\Support\Adapters\Session\MutableSessionClock;
use Zef\Test\Support\Adapters\Session\SessionFileDoubleState;

/**
 * Pins the session port's filesystem answer: the identifier-keyed
 * owner-only record files carrying the written-at reading inside
 * the record, the sliding expiry's lazy refusal at load with its
 * boundary pinned both sides, the idempotent destroy honest under
 * the concurrent-retirement window, the cadenced sweep that
 * retires the abandoned (the cadence the STORE's own marker
 * carries across the per-request composition, and the defect
 * record quarantined into its lane instead of bricking the
 * store's saves -- issue #788, finding 2), the fail-closed decode
 * of a corrupt or hand-edited record at the LOAD the session's own
 * request pays, the depth ceiling honoured at the write so every
 * written record reads back, the read-modify-write lane the
 * issue #781 finding demanded (the load holding the sibling-refused
 * sidecar lane through the save that releases it, the refused walk
 * releasing it on the way out, the one-lane-per-instance posture,
 * and the real cross-process serialization -- two overlapping
 * requests on one session keeping BOTH their writes where the
 * last-write-wins interleave silently lost one), and the engine's
 * own failure arms (the refused write, the unsecurable record, the
 * unfinalisable record, the unremovable record, the unscannable
 * root, the unreadable record) exercised through the namespace's
 * function doubles, armed per test and reset in the tear-down.
 *
 * @internal
 */
#[CoversClass(FileSessionAdapter::class)]
#[CoversClass(SessionRecordDecoder::class)]
#[CoversClass(SessionBagDepth::class)]
#[CoversClass(SessionRecordEncoder::class)]
#[CoversClass(SessionRecordFiles::class)]
#[CoversClass(SessionRecordLoad::class)]
#[CoversClass(SessionRecordExchange::class)]
#[CoversClass(SessionRecordLock::class)]
#[CoversClass(SessionRecordScan::class)]
#[CoversClass(SessionRecordShape::class)]
#[CoversClass(SessionRecordStaging::class)]
#[CoversClass(SessionRecordValues::class)]
#[CoversClass(SessionSweepCadence::class)]
#[CoversClass(SessionRecordQuarantine::class)]
#[CoversClass(SessionRecordRetirement::class)]
#[CoversClass(SessionStoreDirectory::class)]
#[CoversClass(SessionSweep::class)]
final class FileSessionAdapterTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-session-adapter-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        SessionFileDoubleState::reset();
        $this->removeTree($this->directory);
    }

    public function testASaveAndLoadRoundTrip(): void
    {
        $adapter = $this->adapter(1800, 1000);

        $adapter->save('clientOne', ['user' => 7, 'cart' => ['sku' => 'A-1']]);

        self::assertSame(
            ['user' => 7, 'cart' => ['sku' => 'A-1']],
            $adapter->load('clientOne'),
            'the bag round-trips through the record file',
        );
    }

    public function testTheRecordFileIsNamedByTheIdentifierAndCarriesOwnerOnlyPermissions(): void
    {
        $adapter = $this->adapter(1800, 1000);

        $adapter->save('clientOne', ['user' => 7]);

        $record = $this->directory . '/clientOne.json';
        self::assertFileExists($record, 'the record is named by the identifier');
        $permissions = fileperms($record);
        self::assertNotFalse($permissions, 'the record permissions must be readable');
        self::assertSame(
            0,
            $permissions & 0o077,
            'the record is secured owner-only',
        );
        self::assertStringContainsString(
            '"written_at":1000',
            (string) file_get_contents($record),
            'the written-at reading rides inside the record, not on its mtime',
        );
    }

    public function testALoadAnswersNullForAnUnknownIdentifier(): void
    {
        self::assertNull(
            $this->adapter(1800, 1000)->load('clientOne'),
            'the miss is the truth',
        );
    }

    public function testALoadPurgesAnExpiredRecord(): void
    {
        $this->adapter(30, 1000)->save('clientOne', ['user' => 7]);

        self::assertNull(
            $this->adapter(30, 1030)->load('clientOne'),
            'the timeout fully elapsed is expired',
        );
        self::assertFileDoesNotExist(
            $this->directory . '/clientOne.json',
            'the expired record is purged at its own load',
        );
    }

    public function testTheLastInstantBeforeExpiryReads(): void
    {
        $this->adapter(30, 1000)->save('clientOne', ['user' => 7]);

        self::assertSame(
            ['user' => 7],
            $this->adapter(30, 1029)->load('clientOne'),
            'the last instant before the timeout is not expired',
        );
    }

    public function testADestroyRemovesTheRecordAndIsIdempotent(): void
    {
        $adapter = $this->adapter(1800, 1000);
        $adapter->save('clientOne', ['user' => 7]);

        $adapter->destroy('clientOne');
        $adapter->destroy('clientOne');

        self::assertFileDoesNotExist($this->directory . '/clientOne.json');
        self::assertNull($adapter->load('clientOne'), 'the destroyed session is a fresh one');
    }

    public function testADestroyOfANeverSavedIdentifierIsTheNoOpItIs(): void
    {
        $adapter = $this->adapter(1800, 1000);

        $adapter->destroy('clientOne');

        self::assertNull($adapter->load('clientOne'));
    }

    public function testTheSweepRetiresTheAbandonedRecord(): void
    {
        $mutableSessionClock = new MutableSessionClock(1000);
        $fileSessionAdapter = new FileSessionAdapter($this->directory, 30, $mutableSessionClock->now(...));

        $fileSessionAdapter->save('abandoned', ['user' => 7]);
        $fileSessionAdapter->save('returning', ['user' => 8]);

        file_put_contents(
            $this->directory . '/boundary.json',
            '{"data":{"user":3},"written_at":1030}',
        );
        file_put_contents(
            $this->directory . '/last-instant.json',
            '{"data":{"user":4},"written_at":1031}',
        );

        $mutableSessionClock->advanceTo(1060);
        $fileSessionAdapter->save('fresh', ['user' => 9]);

        self::assertFileDoesNotExist(
            $this->directory . '/abandoned.json',
            'the record no load ever revisits is retired by the cadenced sweep',
        );
        self::assertFileDoesNotExist(
            $this->directory . '/returning.json',
            'the sweep judges the written-at reading, not the filename',
        );
        self::assertFileDoesNotExist(
            $this->directory . '/boundary.json',
            'the sweep takes the record whose timeout fully elapsed, the same boundary the load refuses at',
        );
        self::assertFileExists(
            $this->directory . '/last-instant.json',
            'the last instant before the timeout is not swept',
        );
        self::assertFileExists(
            $this->directory . '/fresh.json',
            'the record inside its own timeout stays',
        );
    }

    public function testTheSweepCadenceBoundsTheWalk(): void
    {
        $mutableSessionClock = new MutableSessionClock(1000);
        $fileSessionAdapter = new FileSessionAdapter($this->directory, 30, $mutableSessionClock->now(...));

        $fileSessionAdapter->save('first', ['user' => 7]);

        file_put_contents(
            $this->directory . '/stranded.json',
            '{"data":{"user":1},"written_at":500}',
        );

        $mutableSessionClock->advanceTo(1059);
        $fileSessionAdapter->save('second', ['user' => 8]);

        self::assertFileExists(
            $this->directory . '/stranded.json',
            'the last second inside the cadence rides no walk',
        );

        $mutableSessionClock->advanceTo(1060);
        $fileSessionAdapter->save('third', ['user' => 9]);

        self::assertFileDoesNotExist(
            $this->directory . '/stranded.json',
            'the cadence fully elapsed runs the walk',
        );
    }

    /**
     * The cadence survives the per-request composition the factory
     * answers (issue #788, finding 2): every save-bearing request
     * builds its own adapter over the same store, so the
     * instance-field cadence the first spelling carried reset with
     * every composition -- every request paid a full store walk
     * while the sixty-second cadence promised one walk a minute.
     * The store's own marker is what answers now.
     */
    public function testTheSweepCadenceSurvivesThePerRequestMappingComposition(): void
    {
        $mutableSessionClock = new MutableSessionClock(1000);

        file_put_contents(
            $this->directory . '/stranded.json',
            '{"data":{"user":1},"written_at":500}',
        );

        new FileSessionAdapter($this->directory, 30, $mutableSessionClock->now(...))
            ->save('first', ['user' => 7])
        ;

        self::assertFileDoesNotExist(
            $this->directory . '/stranded.json',
            "the first request's sweep retired the abandoned record",
        );

        file_put_contents(
            $this->directory . '/second-stranded.json',
            '{"data":{"user":2},"written_at":1010}',
        );

        $mutableSessionClock->advanceTo(1010);
        new FileSessionAdapter($this->directory, 30, $mutableSessionClock->now(...))
            ->save('second', ['user' => 8])
        ;

        self::assertFileExists(
            $this->directory . '/second-stranded.json',
            "the fresh composition inside the cadence rides no walk: the store's own marker answers",
        );

        $mutableSessionClock->advanceTo(1070);
        new FileSessionAdapter($this->directory, 30, $mutableSessionClock->now(...))
            ->save('third', ['user' => 9])
        ;

        self::assertFileDoesNotExist(
            $this->directory . '/second-stranded.json',
            'the cadence fully elapsed, the next composition walks',
        );
        self::assertFileExists(
            $this->directory . '/.sweep-cadence',
            "the marker the cadence rides is the store's own artefact",
        );
        self::assertSame(
            '1070',
            mb_trim((string) file_get_contents($this->directory . '/.sweep-cadence')),
            "the marker carries the last sweep's own reading",
        );
    }

    /**
     * The marker's read is fail-open by design: an operator-corrupted
     * or non-numeric marker answers zero, and zero is the walk the
     * store pays -- the cadence is a cost bound, never a correctness
     * one, so the worst case a corrupted marker can inflict is one
     * extra walk, never a missed one.
     */
    public function testACorruptedMarkerAnswersTheWalkTheStorePays(): void
    {
        $mutableSessionClock = new MutableSessionClock(2000);

        file_put_contents($this->directory . '/.sweep-cadence', 'not a reading');
        file_put_contents(
            $this->directory . '/abandoned.json',
            '{"data":{"user":1},"written_at":1000}',
        );

        new FileSessionAdapter($this->directory, 30, $mutableSessionClock->now(...))
            ->save('fresh', ['user' => 7])
        ;

        self::assertFileDoesNotExist(
            $this->directory . '/abandoned.json',
            'the corrupted marker answers zero, the walk the store pays',
        );
    }

    /**
     * The marker's write is best-effort for the same reason: a marker
     * the store cannot lay down leaves the next save sweeping again --
     * the degraded-but-correct cadence, never a refusal that would
     * brick the very saves the cadence exists to cheapen.
     */
    public function testAMarkerTheStoreCannotLayDownLeavesTheNextSaveSweeping(): void
    {
        $mutableSessionClock = new MutableSessionClock(3000);

        // The sequence answers the first rename (the save's own record)
        // with the engine's passthrough and the second (the marker's)
        // with the refusal: the record landed, the marker did not.
        SessionFileDoubleState::$renameAnswers = [null, false];

        new FileSessionAdapter($this->directory, 30, $mutableSessionClock->now(...))
            ->save('first', ['user' => 7])
        ;

        self::assertFileExists(
            $this->directory . '/first.json',
            'the save landed: the marker write never fails the save it rides',
        );
        self::assertFileDoesNotExist(
            $this->directory . '/.sweep-cadence',
            'the marker the store could not lay down is simply absent',
        );
    }

    /**
     * The lane's own filesystem refusal stays loud: the quarantine
     * move itself failing is the store's own failure to name, never a
     * record's defect -- the honest refusal, spelled on the save whose
     * own record already landed.
     */
    public function testAQuarantineTheStoreCannotMakeIsTheNamedRefusal(): void
    {
        file_put_contents($this->directory . '/corrupt.json', 'not json at all');

        // The sequence answers the save's own record rename with the
        // engine passthrough, the marker's with the best-effort
        // refusal the cadence shrugs off, and the quarantine's with
        // the refusal the store must name.
        SessionFileDoubleState::$renameAnswers = [null, false, false];

        try {
            $this->adapter(1800, 1000)->save('present', ['user' => 7]);
            self::fail('The quarantine the store cannot make was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The session file store cannot quarantine the record 'corrupt'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    /**
     * The lane the store cannot open is the same named refusal: the
     * mkdir the store cannot make is the store's own filesystem
     * story, never a record's defect.
     */
    public function testALaneTheStoreCannotOpenIsTheNamedRefusal(): void
    {
        file_put_contents($this->directory . '/corrupt.json', 'not json at all');

        // The mkdir double arms the unopenable-lane refusal without
        // the engine's own warning the real failure would emit.
        SessionFileDoubleState::$mkdirAnswer = false;

        try {
            $this->adapter(1800, 1000)->save('present', ['user' => 7]);
            self::fail('The lane the store cannot open was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The session file store cannot open its quarantine lane.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    /**
     * The marker the store can see but not read still answers zero:
     * the read double refuses while the file exists, and zero is the
     * walk the store pays -- the fail-open cadence the marker's own
     * doctrine spells.
     */
    public function testAMarkerTheStoreCannotReadAnswersTheWalk(): void
    {
        file_put_contents($this->directory . '/.sweep-cadence', '5000');
        file_put_contents(
            $this->directory . '/abandoned.json',
            '{"data":{"user":1},"written_at":1000}',
        );

        SessionFileDoubleState::$fileGetContentsAnswer = false;

        $this->adapter(300, 6000)->save('fresh', ['user' => 7]);

        self::assertFileDoesNotExist(
            $this->directory . '/abandoned.json',
            'the unreadable marker answers zero, the walk the store pays',
        );
    }

    /**
     * The marker's own staging name is the one the best-effort write
     * shrugs off: a refused write leaves the save landing and the
     * marker absent -- the degraded-but-correct cadence, the next save
     * sweeping again. The cadence fold is exercised directly so the
     * refused write is the marker's own, never the record staging the
     * armed double would refuse first.
     */
    public function testAWriteTheMarkerCannotLayDownAnswersTheDegradedCadence(): void
    {
        $sessionSweepCadence = new SessionSweepCadence($this->directory);

        $sessionSweepCadence->ranAt(5000);

        self::assertSame(
            5000,
            $sessionSweepCadence->lastRanAt(),
            'the laid-down marker reads back',
        );

        SessionFileDoubleState::$filePutContentsAnswer = false;

        $sessionSweepCadence->ranAt(6000);

        self::assertSame(
            5000,
            $sessionSweepCadence->lastRanAt(),
            'the marker the store cannot write is simply not there: the reading it last held stands',
        );
    }

    public function testASaveRefusesABagTheRecordCannotCarry(): void
    {
        $adapter = $this->adapter(1800, 1000);

        try {
            $adapter->save('clientOne', ['cart' => new stdClass()]);
            self::fail('The object-holding bag was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The session record cannot carry stdClass at cart.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testASaveRefusesANonFiniteFloat(): void
    {
        $adapter = $this->adapter(1800, 1000);

        try {
            $adapter->save('clientOne', ['ratio' => \NAN]);
            self::fail('The NAN-holding bag was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The session record cannot carry NAN at ratio.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testASaveRefusesAStringThatIsNotValidUtf8(): void
    {
        $adapter = $this->adapter(1800, 1000);

        try {
            $adapter->save('clientOne', ['memo' => "\xB1\x31"]);
            self::fail('The invalid-UTF-8 bag was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The session record cannot carry a string that is not valid UTF-8 at memo.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testASaveRefusesABagPastTheDepthCeiling(): void
    {
        $adapter = $this->adapter(1800, 1000);

        try {
            $adapter->save('clientOne', $this->bagOfDepth(511));
            self::fail('The over-deep bag was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The session record cannot carry a bag nested deeper than 510 levels, 511 given.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testABagAtTheDepthCeilingRoundTrips(): void
    {
        $adapter = $this->adapter(1800, 1000);
        $bag = $this->bagOfDepth(510);

        $adapter->save('clientOne', $bag);

        self::assertSame(
            $bag,
            $this->adapter(1800, 1000)->load('clientOne'),
            'the deepest bag the engine reads back is the deepest bag the write admits',
        );
    }

    public function testFloatsKeepTheirZeroFractionAcrossTheRoundTrip(): void
    {
        $adapter = $this->adapter(1800, 1000);

        $adapter->save('clientOne', ['ratio' => 1.0]);

        $reloaded = $this->adapter(1800, 1000)->load('clientOne');
        self::assertIsArray($reloaded);
        self::assertSame(1.0, $reloaded['ratio']);
        self::assertStringContainsString(
            '"ratio":1.0',
            (string) file_get_contents($this->directory . '/clientOne.json'),
            'a float-kind value stays float-kind in the record',
        );
    }

    public function testAMultibyteBagRoundTrips(): void
    {
        $adapter = $this->adapter(1800, 1000);

        $adapter->save('clientOne', ['memo' => 'héllo']);

        self::assertSame(
            ['memo' => 'héllo'],
            $this->adapter(1800, 1000)->load('clientOne'),
            'a valid UTF-8 string survives the JSON escape both ways',
        );
    }

    public function testALoadRefusesACorruptRecord(): void
    {
        file_put_contents($this->directory . '/clientOne.json', 'not json at all');

        try {
            $this->adapter(1800, 1000)->load('clientOne');
            self::fail('The corrupt record was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The session record 'clientOne' is not valid JSON: Syntax error.",
                $invalidArgumentException->getMessage(),
                "the refusal carries the engine's own reason and its full spelling",
            );
            self::assertSame(
                0,
                $invalidArgumentException->getCode(),
                "the wrapped refusal codes zero, the engine's own previous kept",
            );
        }
    }

    public function testALoadRefusesARecordPastTheDecodeDepthCeiling(): void
    {
        $record = '{"data":' . str_repeat('{"a":', 511) . '0' . str_repeat('}', 511)
            . ',"written_at":1000}';

        file_put_contents($this->directory . '/clientOne.json', $record);

        try {
            $this->adapter(1800, 1000)->load('clientOne');
            self::fail('The over-deep record was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringStartsWith(
                "The session record 'clientOne' is not valid JSON: ",
                $invalidArgumentException->getMessage(),
                "the decode grammar's own depth ceiling refuses the record the write would never have produced",
            );
        }
    }

    #[DataProvider('provideAHandEditedRecordIsRefusedByItsOwnDefectCases')]
    public function testAHandEditedRecordIsRefusedByItsOwnDefect(
        string $record,
        string $spelled,
    ): void {
        file_put_contents($this->directory . '/clientOne.json', $record);

        try {
            $this->adapter(1800, 1000)->load('clientOne');
            self::fail('The hand-edited record was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString(
                $spelled,
                $invalidArgumentException->getMessage(),
            );
        }
    }

    /**
     * @return Iterator<string, array{string, string}>
     */
    public static function provideAHandEditedRecordIsRefusedByItsOwnDefectCases(): iterable
    {
        yield 'a list root' => ['[1, 2]', 'must carry exactly the data and written_at keys'];

        yield 'a missing key' => ['{"data":{}}', 'must carry exactly the data and written_at keys'];

        yield 'an extra key' => [
            '{"data":{},"written_at":1,"extra":2}',
            'must carry exactly the data and written_at keys',
        ];

        yield 'a non-map data key' => [
            '{"data":"flat","written_at":1}',
            'data key must be a map, string given',
        ];

        yield 'a numeric data key' => [
            '{"data":{"0":1},"written_at":1}',
            'data map must be keyed by strings, int given',
        ];

        yield 'a non-integer written-at' => [
            '{"data":{},"written_at":"1000"}',
            'written_at key must be an integer, string given',
        ];

        yield 'a scalar root' => ['7', 'must be a record map, int given'];
    }

    public function testARefusedFinaliseStrandsTheStagingFileTheScansIgnore(): void
    {
        SessionFileDoubleState::$renameAnswers = [false];

        try {
            $this->adapter(1800, 1000)->save('clientOne', ['user' => 7]);
            self::fail('The refused finalise was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The session file store cannot finalise the record 'clientOne.json'.",
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertFileDoesNotExist(
            $this->directory . '/clientOne.json',
            'the refused finalise left no record behind',
        );
        self::assertFileExists(
            $this->directory . '/.clientOne.json',
            'the crash window strands the staging dot-file',
        );
        self::assertNull(
            $this->adapter(1800, 1000)->load('clientOne'),
            'the scan the sweep rides ignores the stranded dot-file',
        );
    }

    public function testARefusedWriteIsRefused(): void
    {
        SessionFileDoubleState::$filePutContentsAnswer = false;

        try {
            $this->adapter(1800, 1000)->save('clientOne', ['user' => 7]);
            self::fail('The refused write was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The session file store cannot write the record 'clientOne.json'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAPartialWriteIsRefused(): void
    {
        SessionFileDoubleState::$filePutContentsAnswer = 3;

        try {
            $this->adapter(1800, 1000)->save('clientOne', ['user' => 7]);
            self::fail('The partial write was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The session file store cannot write the record 'clientOne.json'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARefusedChmodIsRefused(): void
    {
        SessionFileDoubleState::$chmodAnswer = false;

        try {
            $this->adapter(1800, 1000)->save('clientOne', ['user' => 7]);
            self::fail('The refused securing was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The session file store cannot secure the record 'clientOne.json'.",
                $invalidArgumentException->getMessage(),
            );
        } finally {
            unlink($this->directory . '/.clientOne.json');
        }
    }

    public function testAnUnremovableRecordIsRefused(): void
    {
        $adapter = $this->adapter(1800, 1000);
        $adapter->save('clientOne', ['user' => 7]);

        SessionFileDoubleState::$unlinkAnswer = false;

        try {
            $adapter->destroy('clientOne');
            self::fail('The unremovable record was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The session file store cannot remove the record 'clientOne'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordARetiringWorkerTookThroughTheWindowIsTheDestroyedState(): void
    {
        $adapter = $this->adapter(1800, 1000);
        $adapter->save('clientOne', ['user' => 7]);

        SessionFileDoubleState::$fileExistsAnswers = [false];
        SessionFileDoubleState::$unlinkAnswer = false;

        $adapter->destroy('clientOne');

        self::addToAssertionCount(1);
    }

    public function testAnUnreadableRecordIsRefusedAtLoad(): void
    {
        $adapter = $this->adapter(1800, 1000);
        $adapter->save('clientOne', ['user' => 7]);

        SessionFileDoubleState::$fileGetContentsAnswer = false;

        try {
            $adapter->load('clientOne');
            self::fail('The unreadable record was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The session file store cannot read the record 'clientOne'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheSweepSkipsARecordARetiringWorkerTookThroughTheWindow(): void
    {
        SessionFileDoubleState::$scandirAnswer = ['gone.json'];
        SessionFileDoubleState::$fileGetContentsAnswer = false;

        $this->adapter(1800, 1000)->save('present', ['user' => 7]);

        self::addToAssertionCount(1);
    }

    public function testTheSweepQuarantinesARecordItCannotRead(): void
    {
        // The store-wide blast radius the first spelling carried
        // (issue #788, finding 2): a record the sweep cannot read was
        // a refusal the whole save paid -- one unreadable file and
        // every save-bearing request on the store failed. The sweep
        // now retires the defect into the quarantine lane, content
        // preserved, and the store keeps serving. The stale-listing
        // double scopes the walk to the one defect record the read
        // double refuses, so the save's own record rides outside it.
        file_put_contents($this->directory . '/unreadable.json', '{"data":{},"written_at":1}');
        SessionFileDoubleState::$scandirAnswer = ['unreadable.json'];
        SessionFileDoubleState::$fileGetContentsAnswer = false;

        $this->adapter(1800, 1000)->save('zzz-present', ['user' => 7]);

        self::assertFileExists(
            $this->directory . '/zzz-present.json',
            'the save landed: the defect record never bricked the store',
        );
        self::assertFileDoesNotExist(
            $this->directory . '/unreadable.json',
            'the defect record left the record namespace',
        );
        self::assertFileExists(
            $this->directory . '/.quarantine/unreadable.json',
            "the lane keeps the operator's story",
        );
        self::assertSame(
            '{"data":{},"written_at":1}',
            file_get_contents($this->directory . '/.quarantine/unreadable.json'),
            'the lane preserves the content verbatim',
        );
    }

    public function testTheSweepQuarantinesAnUndecodableRecord(): void
    {
        file_put_contents($this->directory . '/corrupt.json', 'not json at all');

        $this->adapter(1800, 1000)->save('present', ['user' => 7]);

        self::assertFileExists(
            $this->directory . '/present.json',
            'the save landed: the undecodable record never bricked the store',
        );
        self::assertFileDoesNotExist(
            $this->directory . '/corrupt.json',
            'the defect record left the record namespace',
        );
        self::assertFileExists(
            $this->directory . '/.quarantine/corrupt.json',
            "the lane keeps the operator's story",
        );
        self::assertSame(
            'not json at all',
            file_get_contents($this->directory . '/.quarantine/corrupt.json'),
            'the lane preserves the content verbatim',
        );
        self::assertNull(
            $this->adapter(30, 1000)->load('corrupt'),
            'the quarantined record answers the miss every retired record answers',
        );
    }

    /**
     * The walk the quarantine serves carries on: a defect record
     * quarantined mid-walk never ends the sweep, so the abandoned
     * records behind it still retire in the same walk.
     */
    public function testTheWalkCarriesOnPastTheQuarantinedRecord(): void
    {
        file_put_contents($this->directory . '/corrupt.json', 'not json at all');
        file_put_contents(
            $this->directory . '/abandoned.json',
            '{"data":{"user":1},"written_at":500}',
        );

        $this->adapter(300, 1000)->save('present', ['user' => 7]);

        self::assertFileExists($this->directory . '/.quarantine/corrupt.json');
        self::assertFileDoesNotExist(
            $this->directory . '/abandoned.json',
            'the records behind the defect still retired in the same walk',
        );
    }

    public function testADirectoryNamedLikeARecordIsSkippedByTheScanAndAnswersAMiss(): void
    {
        mkdir($this->directory . '/aaa.json', 0o700);
        file_put_contents(
            $this->directory . '/zzz.json',
            '{"data":{"user":5},"written_at":500}',
        );

        // One adapter instance per read-modify-write lane: the miss's
        // load and the later save are two lanes over one store, the
        // one-lane-per-instance posture the driver's lock fold owns.
        self::assertNull(
            $this->adapter(30, 1000)->load('aaa'),
            'a directory named like a record is the miss the load answers',
        );

        $this->adapter(30, 1000)->save('clientOne', ['user' => 7]);

        self::assertFileDoesNotExist(
            $this->directory . '/zzz.json',
            'the scan walks past the directory named like a record, never ends at it',
        );
        self::assertDirectoryExists($this->directory . '/aaa.json');
    }

    public function testAStrandedStagingFileIsIgnoredByTheSweepsScan(): void
    {
        file_put_contents($this->directory . '/.stranded.json', 'not json at all');

        $this->adapter(1800, 1000)->save('clientOne', ['user' => 7]);

        self::assertFileExists(
            $this->directory . '/clientOne.json',
            'the save landed',
        );
        self::assertFileExists(
            $this->directory . '/.stranded.json',
            'the stranded dot-file stayed',
        );
    }

    public function testASaveRefusesABagWhoseDepthHidesBehindALeaf(): void
    {
        $adapter = $this->adapter(1800, 1000);

        try {
            $adapter->save('clientOne', ['leaf' => 0, 'k' => $this->bagOfDepth(511)]);
            self::fail('The over-deep bag was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The session record cannot carry a bag nested deeper than 510 levels, 512 given.',
                $invalidArgumentException->getMessage(),
                'the depth walk measures every value, the leaf first among them',
            );
        }
    }

    public function testTheStoreRefusesAMisprovisionedDirectoryAtConstruction(): void
    {
        try {
            $this->adapter(1800, 1000, $this->directory . '-missing');
            self::fail('The missing store directory was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The session file store must be an existing writable non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnUnscannableRootIsRefusedByTheSweep(): void
    {
        SessionFileDoubleState::$scandirAnswer = false;

        try {
            $this->adapter(1800, 1000)->save('clientOne', ['user' => 7]);
            self::fail('The unscannable root was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The session file store directory cannot be scanned.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testALoadHoldsTheLaneUntilTheSaveReleasesIt(): void
    {
        // The issue #781 core span, against the engine's own lock
        // machinery: the load TAKES the sibling-refused lane and the
        // save RELEASES it -- the whole read-modify-write the native
        // `files` handler holds per request, observed through a
        // non-blocking probe the sibling would answer.
        $adapter = $this->adapter(1800, 1000);

        $adapter->load('clientOne');

        self::assertFalse(
            $this->probeLane('clientOne'),
            'the lane is held from the load -- a sibling request is refused it',
        );

        $adapter->save('clientOne', ['user' => 7]);

        self::assertTrue(
            $this->probeLane('clientOne'),
            'the save released the lane -- the sibling request answers it now',
        );
    }

    public function testALoadMissHoldsItsLaneTooUntilTheSaveReleasesIt(): void
    {
        // A fresh session's read-modify-write rides the same lane:
        // the miss the load answered still took it, and the first
        // save -- the settlement's own write for a session that began
        // this request -- releases it.
        $adapter = $this->adapter(1800, 1000);

        self::assertNull($adapter->load('freshClient'));
        self::assertFalse($this->probeLane('freshClient'), 'the miss took the lane');

        $adapter->save('freshClient', ['user' => 7]);

        self::assertTrue($this->probeLane('freshClient'), 'the first save released it');
    }

    public function testADestroyEndsTheLaneTheLoadBegan(): void
    {
        $adapter = $this->adapter(1800, 1000);
        $adapter->save('clientOne', ['user' => 7]);
        $adapter->load('clientOne');

        self::assertFalse($this->probeLane('clientOne'), 'the destroy-load took the lane');

        $adapter->destroy('clientOne');

        self::assertTrue(
            $this->probeLane('clientOne'),
            'the destroy released the lane the load began',
        );
    }

    public function testARefusedLoadReleasesTheLaneOnTheWayOut(): void
    {
        // A poisoned store stays loud on every later load, never a
        // lane the process silently cannot take again: the refused
        // walk releases the lane on its way out.
        file_put_contents(
            $this->directory . '/clientOne.json',
            'not json at all',
        );

        $adapter = $this->adapter(1800, 1000);

        try {
            $adapter->load('clientOne');
            self::fail('The corrupt record was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString('clientOne', $invalidArgumentException->getMessage());
        }

        self::assertTrue(
            $this->probeLane('clientOne'),
            'the refused load released the lane on the way out',
        );
    }

    public function testARefusedSaveReleasesTheLaneOnTheWayOut(): void
    {
        // The release rides the save's finally: a write the store
        // refuses never strands the lane in this process -- the
        // sibling request waiting on the lane is answered either way.
        $adapter = $this->adapter(1800, 1000);
        $adapter->load('clientOne');

        SessionFileDoubleState::$filePutContentsAnswer = false;

        try {
            $adapter->save('clientOne', ['user' => 7]);
            self::fail('The refused write was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The session file store cannot write the record 'clientOne.json'.",
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertTrue(
            $this->probeLane('clientOne'),
            'the refused save released the lane on the way out',
        );
    }

    public function testAMultiLaneInterleaveOverOneInstanceIsRefused(): void
    {
        // One instance, one read-modify-write at a time -- the native
        // handler's own posture. The port's sequential grammar is
        // untouched (the next different identifier's load or save
        // after a settled lane), the INTERLEAVE is what the driver
        // refuses, naming the lane it holds.
        $adapter = $this->adapter(1800, 1000);
        $adapter->load('clientOne');

        try {
            $adapter->save('clientTwo', ['user' => 7]);
            self::fail('The interleave over one instance was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The session file store holds one read-modify-write lane at a time'
                . " (held: 'clientOne', asked: 'clientTwo').",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testASiblingRequestBlocksOnTheLaneUntilTheRequestsSave(): void
    {
        // The cross-process half of the lane's reality: the marker
        // child takes the same sidecar lock the driver takes, blocks
        // while the adapter holds the lane, and marks only after the
        // save releases it -- the causality the assertion reads both
        // sides of (the marker is absent before the release because
        // the child cannot hold the lane; present after because it
        // then did).
        $marker = $this->directory . '-marker';
        $adapter = $this->adapter(1800, 1000);
        $adapter->load('clientOne');

        $child = $this->spawnChild(__DIR__ . '/../../Support/Adapters/Session/SessionLaneMarkerChild.php', [
            $this->directory . '/.clientOne.lock',
            $marker,
        ]);

        usleep(300_000);

        self::assertFileDoesNotExist(
            $marker,
            'the sibling is still blocked on the lane the request holds',
        );

        $adapter->save('clientOne', ['user' => 7]);

        $this->reapChild($child);
        self::assertSame(
            'acquired',
            file_get_contents($marker),
            'the sibling acquired the lane only after the request released it',
        );
    }

    public function testTwoOverlappingRequestsOnOneSessionKeepBothTheirWrites(): void
    {
        // The issue #781 repro itself, over the real driver in the
        // engine's own children: two sibling requests on one session
        // (two tabs, a double-submit, an AJAX poller) each read under
        // the lane, merge their own write into whatever the sibling
        // already wrote, and save under the lane -- whichever child
        // runs second blocks on the first child's lane and reads the
        // first child's write, so BOTH writes survive, where the
        // last-write-wins interleave silently lost one.
        $first = $this->spawnChild(__DIR__ . '/../../Support/Adapters/Session/SessionLaneChild.php', [
            $this->directory,
            'clientOne',
            'firstTab',
        ]);
        $second = $this->spawnChild(__DIR__ . '/../../Support/Adapters/Session/SessionLaneChild.php', [
            $this->directory,
            'clientOne',
            'secondTab',
        ]);

        $this->reapChild($first);
        $this->reapChild($second);

        $bag = $this->adapter(1800, 1000)->load('clientOne');

        self::assertEqualsCanonicalizing(
            ['firstTab' => true, 'secondTab' => true],
            $bag,
            'both overlapping requests kept their writes -- the lane serialized the read-modify-writes'
            . ' (whichever child won the lane first, its key landed first)',
        );
    }

    /**
     * Builds the adapter over the test's own directory, with the
     * frozen clock the expiry and cadence tests read.
     */
    private function adapter(int $idleTimeout, ?int $now = null, ?string $directory = null): FileSessionAdapter
    {
        if ($now === null) {
            return new FileSessionAdapter($directory ?? $this->directory, $idleTimeout);
        }

        return new FileSessionAdapter(
            $directory ?? $this->directory,
            $idleTimeout,
            static fn (): int => $now,
        );
    }

    /**
     * Builds a bag nested to the asked depth: a flat map is one
     * level, each wrap one deeper.
     *
     * @return array<string, mixed>
     */
    private function bagOfDepth(int $depth): array
    {
        $bag = ['leaf' => 0];

        for ($wraps = 1; $wraps < $depth; ++$wraps) {
            $bag = ['k' => $bag];
        }

        return $bag;
    }

    /**
     * One sibling's non-blocking probe of the identifier's lane: the
     * engine's own lock machinery answering whether the sidecar is
     * held -- the observable the lane's whole span family reads.
     */
    private function probeLane(string $id): bool
    {
        $handle = fopen($this->directory . '/.' . $id . '.lock', 'cb');

        if ($handle === false) {
            self::fail('The lane sidecar could not be opened for the probe.');
        }

        $taken = !flock($handle, \LOCK_EX | \LOCK_NB);
        flock($handle, \LOCK_UN);
        fclose($handle);

        return !$taken;
    }

    /**
     * Spawns one engine child over the array-command form: the
     * command and its arguments as the process's own argv, no shell
     * wrapper between the parent and the binary -- the same
     * discipline the OTLP mock server and the unpositionable
     * stream's own children carry.
     *
     * @param list<string> $arguments the child script's own argv, the
     *                                script path first
     *
     * @return resource the spawned process
     */
    private function spawnChild(string $script, array $arguments)
    {
        $command = array_merge([\PHP_BINARY, $script], $arguments);
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if ($process === false) {
            self::fail('The lane child could not be spawned.');
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return $process;
    }

    /**
     * Reaps one spawned child: the wait that proves it exited, with
     * the exit code the sibling's own verdict rides.
     *
     * @param resource $process the spawned process
     */
    private function reapChild($process): int
    {
        $exit = proc_close($process);

        self::assertSame(0, $exit, 'the lane child completed its own round trip');

        return $exit;
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
