<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Session;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Session\SessionRecordFiles;
use Zef\Framework\Adapters\Session\SessionRecordLock;
use Zef\Framework\Adapters\Session\SessionRecordScan;
use Zef\Test\Support\Adapters\Session\SessionFileDoubleState;

/**
 * Pins the session record's read-modify-write lane on its own terms:
 * the sidecar companion the lane locks (never the record file, whose
 * one-rename write would release the inode's own lock), the same
 * identifier's second acquire as the lane continuing, the different
 * identifier's acquire while a lane is held as the one-lane posture's
 * own refusal, the idempotent release, the sidecar's own visibility
 * (a dot-file the store's scan never sees, carrying no bytes at all,
 * forever), and the lane being a REAL flock a sibling's non-blocking
 * probe is refused by while it is held -- the issue #781 finding's
 * whole answer, observed against the engine's own lock machinery.
 *
 * @internal
 */
#[CoversClass(SessionRecordLock::class)]
#[CoversClass(SessionRecordFiles::class)]
#[CoversClass(SessionRecordScan::class)]
final class SessionRecordLockTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-session-lane-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        SessionFileDoubleState::reset();
        $this->removeTree($this->directory);
    }

    public function testTheLaneRidesTheSidecarNotTheRecord(): void
    {
        $sessionRecordFiles = new SessionRecordFiles($this->directory);
        $sessionRecordLock = new SessionRecordLock($sessionRecordFiles);

        $sessionRecordLock->acquire('clientOne');
        $sessionRecordLock->release();

        self::assertFileExists(
            $this->directory . '/.clientOne.lock',
            'the lane rides its own sidecar companion',
        );
        self::assertFileDoesNotExist(
            $this->directory . '/clientOne.json',
            'the lane never creates the record itself',
        );
    }

    public function testASiblingProbeIsRefusedWhileTheLaneIsHeldAndAnsweredAfterTheRelease(): void
    {
        $sessionRecordLock = new SessionRecordLock(new SessionRecordFiles($this->directory));
        $sidecar = $this->directory . '/.clientOne.lock';

        $sessionRecordLock->acquire('clientOne');

        self::assertFalse(
            $this->probeNonBlocking($sidecar),
            'a sibling is refused the lane while it is held -- the real flock',
        );

        $sessionRecordLock->release();

        self::assertTrue(
            $this->probeNonBlocking($sidecar),
            'the released lane answers the sibling immediately',
        );
    }

    public function testTheSameIdentifierAcquireIsTheLaneContinuing(): void
    {
        $sessionRecordLock = new SessionRecordLock(new SessionRecordFiles($this->directory));
        $sidecar = $this->directory . '/.clientOne.lock';

        $sessionRecordLock->acquire('clientOne');
        $sessionRecordLock->acquire('clientOne');

        self::assertFalse(
            $this->probeNonBlocking($sidecar),
            'the lane continues -- the second acquire kept it held',
        );

        $sessionRecordLock->release();

        self::assertTrue(
            $this->probeNonBlocking($sidecar),
            'the release ends the lane',
        );
    }

    public function testADifferentIdentifierAcquireIsRefusedWhileALaneIsHeld(): void
    {
        $sessionRecordLock = new SessionRecordLock(new SessionRecordFiles($this->directory));
        $sessionRecordLock->acquire('clientOne');

        try {
            $sessionRecordLock->acquire('clientTwo');
            self::fail('A second lane over one instance must be refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The session file store holds one read-modify-write lane at a time'
                . " (held: 'clientOne', asked: 'clientTwo').",
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(
            'clientOne',
            $this->laneStillHeldFor('clientOne'),
            'the refusal never corrupts the lane it refused over',
        );
        self::assertTrue(
            $this->probeNonBlocking($this->directory . '/.clientTwo.lock'),
            'the refused identifier never had its own lane taken',
        );

        $sessionRecordLock->release();
    }

    public function testTheReleaseIsIdempotentAndTheLaneAnswersANewIdentifier(): void
    {
        $sessionRecordLock = new SessionRecordLock(new SessionRecordFiles($this->directory));
        $secondSidecar = $this->directory . '/.clientTwo.lock';

        $sessionRecordLock->acquire('clientOne');
        $sessionRecordLock->release();
        $sessionRecordLock->release();

        $sessionRecordLock->acquire('clientTwo');

        self::assertFalse(
            $this->probeNonBlocking($secondSidecar),
            'the next lane begins after the idempotent releases',
        );

        $sessionRecordLock->release();
    }

    public function testTheSidecarIsInvisibleToTheStoresScanAndCarriesNoBytes(): void
    {
        $sessionRecordFiles = new SessionRecordFiles($this->directory);
        $sessionRecordLock = new SessionRecordLock($sessionRecordFiles);
        $sessionRecordLock->acquire('clientOne');
        $sessionRecordLock->release();

        self::assertSame(
            [],
            new SessionRecordScan($this->directory)->recordFilenames(),
            'the sidecar dot-file is never a record the walks see',
        );
        self::assertSame(
            '',
            file_get_contents($this->directory . '/.clientOne.lock'),
            'the companion carries no bytes at all, forever',
        );
    }

    public function testAnUnopenableCompanionIsTheLanesOwnRefusal(): void
    {
        SessionFileDoubleState::$fopenAnswer = false;

        $sessionRecordLock = new SessionRecordLock(new SessionRecordFiles($this->directory));

        try {
            $sessionRecordLock->acquire('clientOne');
            self::fail('The unopenable companion was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The session file store cannot open the read-modify-write lane 'clientOne'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnUntakeableLockIsTheLanesOwnRefusal(): void
    {
        SessionFileDoubleState::$flockAnswer = false;

        $sessionRecordLock = new SessionRecordLock(new SessionRecordFiles($this->directory));

        try {
            $sessionRecordLock->acquire('clientOne');
            self::fail('The untakeable lock was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The session file store cannot lock the read-modify-write lane 'clientOne'.",
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertTrue(
            $this->probeNonBlocking($this->directory . '/.clientOne.lock'),
            'the refused lock left no lane held -- the companion closed on the way out',
        );
    }

    /**
     * One sibling's non-blocking probe of the sidecar: the engine's
     * own lock machinery answering whether the lane is held -- the
     * observable every adapter-level pin in this family reads.
     */
    private function probeNonBlocking(string $sidecar): bool
    {
        $handle = fopen($sidecar, 'cb');

        if ($handle === false) {
            self::fail('The sidecar could not be opened for the probe.');
        }

        $taken = !flock($handle, \LOCK_EX | \LOCK_NB);
        flock($handle, \LOCK_UN);
        fclose($handle);

        return !$taken;
    }

    /**
     * The identifier whose lane the engine's own lock machinery says
     * is held: the probe family answered for -- the observable the
     * fold's own one-lane vocabulary pins read.
     */
    private function laneStillHeldFor(string $id): string
    {
        return $this->probeNonBlocking($this->directory . '/.' . $id . '.lock') ? '' : $id;
    }

    /**
     * Removes the store root the test created, dot-files included.
     */
    private function removeTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . \DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }

        rmdir($directory);
    }
}
