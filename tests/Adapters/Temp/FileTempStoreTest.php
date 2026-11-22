<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Temp;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Temp\FileTempStore;
use Zef\Framework\Adapters\Temp\TempRetirement;
use Zef\Framework\Adapters\Temp\TempScratchScan;
use Zef\Framework\Adapters\Temp\TempStaging;
use Zef\Framework\Adapters\Temp\TempStoreDirectory;
use Zef\Framework\Adapters\Temp\TempSweep;
use Zef\Framework\Adapters\Temp\TempSweepCadence;
use Zef\Test\Support\Adapters\Temp\TempFileDoubleState;
use Zef\Test\Support\Adapters\Temp\TempTemporaryTree;

/**
 * Pins the temp lane's writer with its cadenced sweep: the
 * landing's own grammar (thirty-two hexadecimal characters, no
 * host spelling anywhere in it, the payload's own bytes
 * verbatim -- the multibyte byte-exact pin the '8bit' identity
 * demands), the sweep's mortality boundary (the scratch swept
 * exactly when its own retention fully elapses, the last
 * instant before it is not), the cadence's own memory (the
 * marker persisted in the lane, the walk once per cadence
 * riding a write the process was already paying, the
 * recomposition the memory survives, the corrupted marker the
 * walk answers), the scan's own grammar (the dot-files the
 * marker and the stranded staging ride, the directories skipped
 * without ending the walk), and the construction-time bounds
 * (the retention of at least one second, the directory posture,
 * the retirement idiom's own blast radius -- the vanished
 * scratch the retired state itself, the un-unlinkable scratch
 * the loud refusal, never a bricked write).
 *
 * @internal
 */
#[CoversClass(FileTempStore::class)]
#[CoversClass(TempStaging::class)]
#[CoversClass(TempStoreDirectory::class)]
#[CoversClass(TempScratchScan::class)]
#[CoversClass(TempSweep::class)]
#[CoversClass(TempSweepCadence::class)]
#[CoversClass(TempRetirement::class)]
final class FileTempStoreTest extends TestCase
{
    private string $directory;

    /** @var array{bool, mixed} whether DOCUMENT_ROOT existed and its previous value */
    private array $previousDocumentRoot = [false, null];

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-temp-writer-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        TempFileDoubleState::reset();

        $this->restoreDocumentRoot();

        if (is_link($this->directory . '-link')) {
            unlink($this->directory . '-link');
        }

        if (is_dir($this->directory)) {
            chmod($this->directory, 0o700);
        }

        TempTemporaryTree::remove($this->directory);
    }

    public function testALandingWriteLandsUnderTheWritersOwnName(): void
    {
        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);

        $path = $fileTempStore->write('scratch payload');

        self::assertSame(
            realpath($this->directory),
            \dirname($path),
            'the landed path answers the resolved lane address',
        );
        self::assertSame(1, preg_match('/^[a-f0-9]{32}$/D', basename($path)));
        self::assertFileExists($path);
        self::assertSame('scratch payload', file_get_contents($path));
        clearstatcache();
        $permissions = fileperms($path);
        self::assertNotFalse($permissions);
        self::assertSame(
            0o600,
            $permissions & 0o777,
            'the landed scratch carries the owner-only mode the staging discipline secures',
        );
    }

    public function testAnEmptyPayloadLandsAsItsOwnEmptyScratch(): void
    {
        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);

        $path = $fileTempStore->write('');

        self::assertFileExists($path);
        self::assertSame('', file_get_contents($path));
    }

    public function testAMultibytePayloadLandsByteExact(): void
    {
        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);

        $path = $fileTempStore->write('héllo wörld — ✓');

        self::assertFileExists($path);
        self::assertSame('héllo wörld — ✓', file_get_contents($path));
    }

    public function testAFreshScratchSurvivesTheSweepThatRidesTheWrite(): void
    {
        $this->prePlaceScratch('fresh-scratch', 999);

        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);
        $fileTempStore->write('riding write');

        self::assertFileExists(
            $this->directory . '/fresh-scratch',
            'a scratch one instant before its retention is kept',
        );
    }

    public function testAnElapsedScratchRetiresOnTheRide(): void
    {
        $this->prePlaceScratch('stale-scratch', 500);

        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);
        $fileTempStore->write('riding write');

        self::assertFileDoesNotExist(
            $this->directory . '/stale-scratch',
            'a scratch whose retention fully elapsed is retired',
        );
    }

    public function testAScratchExactlyAtTheRetentionRetires(): void
    {
        $this->prePlaceScratch('boundary-scratch', 900);

        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);
        $fileTempStore->write('riding write');

        self::assertFileDoesNotExist($this->directory . '/boundary-scratch');
    }

    public function testAScratchOneInstantBeforeTheRetentionSurvives(): void
    {
        $this->prePlaceScratch('just-fresh-scratch', 901);

        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);
        $fileTempStore->write('riding write');

        self::assertFileExists($this->directory . '/just-fresh-scratch');
    }

    public function testAWriteInsideTheCadenceRidesNoWalk(): void
    {
        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);
        $fileTempStore->write('first write');

        $this->prePlaceScratch('stale-scratch', 500);

        $fileInside = new FileTempStore($this->directory, 100, static fn (): int => 1050);
        $fileInside->write('second write');

        self::assertFileExists(
            $this->directory . '/stale-scratch',
            'a write inside the cadence rides no lane walk',
        );

        $filePast = new FileTempStore($this->directory, 100, static fn (): int => 1070);
        $filePast->write('third write');

        self::assertFileDoesNotExist(
            $this->directory . '/stale-scratch',
            'the write past the cadence pays the walk the cadence bounds',
        );
    }

    public function testTheCadenceMemorySurvivesTheStoresRecomposition(): void
    {
        $firstStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);
        $firstStore->write('first store write');

        $this->prePlaceScratch('stale-scratch', 500);

        $secondStore = new FileTempStore($this->directory, 100, static fn (): int => 1050);
        $secondStore->write('second store write');

        self::assertFileExists(
            $this->directory . '/stale-scratch',
            'the cadence marker persisted in the lane answers the fresh composition',
        );
    }

    public function testACorruptedMarkerAnswersZeroAndPaysTheWalk(): void
    {
        file_put_contents($this->directory . '/.sweep-cadence', 'garbage');
        $this->prePlaceScratch('stale-scratch', 500);

        self::assertSame(
            0,
            (new TempSweepCadence($this->directory))->lastRanAt(),
            'a corrupted marker answers the zero reading itself',
        );

        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);
        $fileTempStore->write('riding write');

        self::assertFileDoesNotExist(
            $this->directory . '/stale-scratch',
            'an unreadable marker answers zero -- the walk the store pays',
        );
    }

    public function testTheSweepNeverTakesTheLanesOwnDotFiles(): void
    {
        file_put_contents($this->directory . '/.operator-material', 'material');
        file_put_contents($this->directory . '/.stranded.staging', 'stranded');

        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 100000);
        $fileTempStore->write('riding write');

        self::assertFileExists(
            $this->directory . '/.operator-material',
            'the scan skips the dot-files the operator owns',
        );
        self::assertFileExists($this->directory . '/.stranded.staging');
        self::assertFileExists(
            $this->directory . '/.sweep-cadence',
            'the cadence marker rides the same blind spot',
        );
    }

    public function testADirectoryInTheLaneIsSkippedWithoutEndingTheWalk(): void
    {
        mkdir($this->directory . '/nested-dir');
        $this->prePlaceScratch('stale-scratch', 500);

        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);
        $fileTempStore->write('riding write');

        self::assertFileDoesNotExist(
            $this->directory . '/stale-scratch',
            'the walk carries on past the directory it skips',
        );
        self::assertDirectoryExists($this->directory . '/nested-dir');
    }

    public function testARefusedStagingWritePropagatesItsOwnRefusal(): void
    {
        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);

        chmod($this->directory, 0o500);

        set_error_handler(static fn (): bool => true);

        try {
            try {
                $fileTempStore->write('never lands');
                self::fail('A refused staging write must propagate its own refusal.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertStringContainsString(
                    'The temp file store cannot write the scratch',
                    $invalidArgumentException->getMessage(),
                );
            }
        } finally {
            restore_error_handler();
            chmod($this->directory, 0o700);
        }
    }

    public function testAnUnsecurableScratchRefusesTheWrite(): void
    {
        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);

        TempFileDoubleState::$chmodAnswer = false;

        try {
            $fileTempStore->write('never secured');
            self::fail('An unsecurable scratch must refuse the write.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString(
                'The temp file store cannot secure the scratch',
                $invalidArgumentException->getMessage(),
            );
        }

        $entries = scandir($this->directory);
        self::assertNotFalse($entries);
        $stranded = array_values(array_diff($entries, ['.', '..']));

        self::assertCount(
            1,
            $stranded,
            'the refused staging strands exactly one file',
        );
        self::assertStringStartsWith(
            '.',
            $stranded[0],
            'the stranded staging is the discipline\'s own leading-dot file, never the landed name',
        );
    }

    public function testAOneSecondRetentionWritesItsOwnScratch(): void
    {
        $fileTempStore = new FileTempStore($this->directory, 1, static fn (): int => 1000);

        $path = $fileTempStore->write('one second is its own acceptable bound');

        self::assertFileExists(
            $path,
            'the one-second retention constructs and writes its own scratch',
        );
    }

    public function testTheScanAnswersTheScratchAloneNeverTheLaneDirectory(): void
    {
        $this->prePlaceScratch('a-scratch', 999);
        mkdir($this->directory . '/nested-dir');

        self::assertSame(
            ['a-scratch'],
            (new TempScratchScan($this->directory))->scratchFilenames(),
            'the scan answers the scratch alone -- a container the lane never provisioned never joins the walk',
        );
    }

    public function testAWriteExactlyAtTheCadencePaysTheWalk(): void
    {
        file_put_contents($this->directory . '/.sweep-cadence', '940');
        $this->prePlaceScratch('stale-scratch', 500);

        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);
        $fileTempStore->write('the cadence elapsed exactly');

        self::assertFileDoesNotExist(
            $this->directory . '/stale-scratch',
            'a write exactly at the cadence boundary pays the walk -- the last instant before it does not',
        );
    }

    public function testAWebServedLaneRefusesTheConstruction(): void
    {
        $this->setDocumentRoot($this->directory);

        try {
            new FileTempStore($this->directory, 100);
            self::fail('A lane equal to the document root must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The storage root must be outside the public document root.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAWorldTraversableMaskRefusesTheConstruction(): void
    {
        chmod($this->directory, 0o701);

        try {
            try {
                new FileTempStore($this->directory, 100);
                self::fail('A world-traversable mask must refuse the construction.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    'The temp file store directory must have owner-only permissions.',
                    $invalidArgumentException->getMessage(),
                );
            }
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    public function testAnUnfinalisableScratchRefusesTheWrite(): void
    {
        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);

        TempFileDoubleState::$renameAnswer = false;

        try {
            $fileTempStore->write('never finalised');
            self::fail('An unfinalisable scratch must refuse the write.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringContainsString(
                'The temp file store cannot finalise the scratch',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnUnscannableLaneRefusesTheSweepsOwnRide(): void
    {
        $fileTempStore = new FileTempStore($this->directory, 100, static fn (): int => 1000);

        TempFileDoubleState::$scandirAnswer = false;

        try {
            $fileTempStore->write('riding an unscannable lane');
            self::fail('An unscannable lane must refuse the sweep riding the write.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The temp file store directory cannot be scanned.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnUnresolvableLaneAddressRefusesTheConstruction(): void
    {
        TempFileDoubleState::$realpathAnswer = false;

        try {
            new FileTempStore($this->directory, 100);
            self::fail('An unresolvable lane address must refuse the construction.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The temp file store directory cannot be resolved.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testASymlinkedLaneRefusesTheConstruction(): void
    {
        symlink($this->directory, $this->directory . '-link');

        try {
            new FileTempStore($this->directory . '-link', 100);
            self::fail('A symlinked lane must refuse the construction.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The temp file store must be an existing writable non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnUnwritableLaneRefusesTheConstruction(): void
    {
        chmod($this->directory, 0o500);

        try {
            try {
                new FileTempStore($this->directory, 100);
                self::fail('An unwritable lane must refuse the construction.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    'The temp file store must be an existing writable non-symlink directory.',
                    $invalidArgumentException->getMessage(),
                );
            }
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    public function testAPermissiveMaskRefusesTheConstruction(): void
    {
        chmod($this->directory, 0o750);

        try {
            try {
                new FileTempStore($this->directory, 100);
                self::fail('A permissive mask must refuse the construction.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    'The temp file store directory must have owner-only permissions.',
                    $invalidArgumentException->getMessage(),
                );
            }
        } finally {
            chmod($this->directory, 0o700);
        }
    }

    public function testAMarkerWhoseRenameRefusesCleansItsOwnStaging(): void
    {
        TempFileDoubleState::$renameAnswer = false;

        $tempSweepCadence = new TempSweepCadence($this->directory);
        $tempSweepCadence->ranAt(12345);

        self::assertFileDoesNotExist(
            $this->directory . '/.sweep-cadence',
            'the refused rename leaves no marker behind',
        );
        self::assertFileDoesNotExist(
            $this->directory . '/.sweep-cadence.staging',
            'the staging file the failed rename stranded is cleaned away',
        );
        self::assertSame(
            0,
            $tempSweepCadence->lastRanAt(),
            'the next read answers the absent marker with the walk the store pays',
        );
    }

    public function testARetentionBelowOneSecondRefusesTheConstruction(): void
    {
        try {
            new FileTempStore($this->directory, 0);
            self::fail('A retention below one second must refuse the construction.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The temp store retention must be at least one second.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARefusingDirectoryRefusesTheConstruction(): void
    {
        try {
            new FileTempStore($this->directory . '-missing', 100);
            self::fail('A refusing directory must refuse the construction.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The temp file store must be an existing writable non-symlink directory.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAVanishedScratchIsTheRetiredStateItself(): void
    {
        $tempRetirement = new TempRetirement($this->directory, 100);

        $tempRetirement->retire('never-was-there', 100000);

        self::assertFileDoesNotExist($this->directory . '/never-was-there');
    }

    public function testAnUnUnlinkableScratchRefusesLoudlyNeverBrickingTheWrite(): void
    {
        $this->prePlaceScratch('stuck-scratch', 500);

        chmod($this->directory, 0o500);

        set_error_handler(static fn (): bool => true);

        try {
            try {
                $tempRetirement = new TempRetirement($this->directory, 100);
                $tempRetirement->retire('stuck-scratch', 100000);
                self::fail('An un-unlinkable scratch must refuse loudly.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(
                    "The temp file store cannot remove the scratch 'stuck-scratch'.",
                    $invalidArgumentException->getMessage(),
                );
            }
        } finally {
            restore_error_handler();
            chmod($this->directory, 0o700);
        }
    }

    public function testAScratchASiblingTookBetweenTheFailedUnlinkAndTheRecheckIsTheRetiredState(): void
    {
        $this->prePlaceScratch('taken-scratch', 500);

        TempFileDoubleState::$unlinkAnswer = false;
        TempFileDoubleState::$fileExistsAnswer = false;

        $tempRetirement = new TempRetirement($this->directory, 100);
        $tempRetirement->retire('taken-scratch', 100000);

        self::assertFileExists(
            $this->directory . '/taken-scratch',
            'the retired state arrived through the concurrent window, never a refusal',
        );
    }

    public function testAMarkerTheLaneCannotLayDownAnswersTheDegradedCadence(): void
    {
        mkdir($this->directory . '/.sweep-cadence');

        set_error_handler(static fn (): bool => true);

        try {
            $tempSweepCadence = new TempSweepCadence($this->directory);
            $tempSweepCadence->ranAt(12345);
        } finally {
            restore_error_handler();
        }

        self::assertDirectoryExists(
            $this->directory . '/.sweep-cadence',
            'the marker the lane cannot lay down never refuses',
        );
        self::assertFileDoesNotExist(
            $this->directory . '/.sweep-cadence.staging',
            'the staging file the failed rename stranded is cleaned away',
        );
    }

    public function testAFailedMarkerRenameAndUnlinkStrandTheStagingFile(): void
    {
        TempFileDoubleState::$renameAnswer = false;
        TempFileDoubleState::$unlinkAnswer = false;

        $tempSweepCadence = new TempSweepCadence($this->directory);
        $tempSweepCadence->ranAt(12345);

        self::assertFileExists(
            $this->directory . '/.sweep-cadence.staging',
            'the failed rename strands the staging file the one-rename discipline writes through',
        );
        self::assertFileDoesNotExist(
            $this->directory . '/.sweep-cadence',
            'the refused rename leaves no marker behind',
        );
    }

    /**
     * Lays one pre-placed scratch down with its own modified time.
     *
     * @param string $filename   the scratch's own filename
     * @param int    $modifiedAt the modified reading the judgment measures
     */
    private function prePlaceScratch(string $filename, int $modifiedAt): void
    {
        $path = $this->directory . '/' . $filename;

        file_put_contents($path, 'pre-placed');
        touch($path, $modifiedAt);
    }

    /**
     * Arms the SAPI document root the shared fold reads.
     */
    private function setDocumentRoot(string $documentRoot): void
    {
        $this->previousDocumentRoot = [
            \array_key_exists('DOCUMENT_ROOT', $_SERVER),
            $_SERVER['DOCUMENT_ROOT'] ?? null,
        ];

        $_SERVER['DOCUMENT_ROOT'] = $documentRoot;
    }

    /**
     * Restores the SAPI document root the test may have armed.
     */
    private function restoreDocumentRoot(): void
    {
        [$existed, $previous] = $this->previousDocumentRoot;

        if ($existed) {
            $_SERVER['DOCUMENT_ROOT'] = $previous;
        } else {
            unset($_SERVER['DOCUMENT_ROOT']);
        }
    }
}
