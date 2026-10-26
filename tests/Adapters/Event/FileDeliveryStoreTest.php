<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Event;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Event\FileDeliveryStore;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\ListenerIdentity;
use Zef\Test\Support\Adapters\Event\DeliveryFileDoubleState;

/**
 * Pins the filesystem answer to the persistence port: the
 * owner-only entry files named by their identities, the
 * read-only take with its due ordering, the idempotent settle,
 * the capacity backpressure with the counts spelled, the
 * mid-write compensation that leaves a refused batch
 * unanswerable-by-the-store, the dead-letter lane an operator
 * reads, and the fail-closed decode of a corrupt or hand-edited
 * record. The engine's own failure arms (the refused write, the
 * unremovable entry, the unscannable root) are exercised through
 * the namespace's function doubles, armed per test and reset in
 * the tear-down.
 *
 * @internal
 */
#[CoversClass(FileDeliveryStore::class)]
final class FileDeliveryStoreTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zef-file-delivery-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
    }

    #[Override]
    protected function tearDown(): void
    {
        DeliveryFileDoubleState::reset();
        $this->removeTree($this->directory);
    }

    public function testACapacityBelowOneIsRefused(): void
    {
        try {
            new FileDeliveryStore($this->directory, 0);
            self::fail('The zero capacity was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "A delivery store's capacity must be at least one, got 0.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testASavedEntryIsOneOwnerOnlyFileNamedByItsIdentity(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);

        $fileDeliveryStore->save($envelope);

        $path = $this->directory . '/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json';

        self::assertFileExists($path);
        self::assertSame(0o600, fileperms($path) & 0o777);
        self::assertSame(1, $fileDeliveryStore->pendingCount());

        $decoded = $fileDeliveryStore->takeDue(0);

        self::assertCount(1, $decoded);
        $this->assertEnvelopeMatches($envelope, $decoded[0]);
    }

    public function testTheCapacityRefusalSpellsTheCounts(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 1);
        $fileDeliveryStore->save($this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]));

        try {
            $fileDeliveryStore->save(
                $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 1]),
                $this->envelope('cccccccccccccccccccccccccccccccc', ['amount' => 2]),
            );
            self::fail('The overflowing batch was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store is full: capacity 1, occupied 1, demanded'
                . ' 2 -- drain before deferring more.',
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(1, $fileDeliveryStore->pendingCount());
    }

    public function testARefusedMidWriteBatchIsCompensatedWhole(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        $written = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $refused = $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 1]);

        DeliveryFileDoubleState::$renameAnswers = [true, false];

        try {
            $fileDeliveryStore->save($written, $refused);
            self::fail('The refused mid-write batch was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store cannot finalise the record'
                . " 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.json'.",
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(0, $fileDeliveryStore->pendingCount());
        self::assertFileDoesNotExist(
            $this->directory . '/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json',
        );
    }

    public function testARefusedWriteLeavesTheStoreUnchanged(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        DeliveryFileDoubleState::$filePutContentsAnswer = false;

        try {
            $fileDeliveryStore->save($this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]));
            self::fail('The refused write was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store cannot write the record'
                . " 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json'.",
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(0, $fileDeliveryStore->pendingCount());
    }

    public function testAPartialWriteIsRefusedAsAWriteFailure(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        DeliveryFileDoubleState::$filePutContentsAnswer = 3;

        try {
            $fileDeliveryStore->save($this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]));
            self::fail('The partial write was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store cannot write the record'
                . " 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json'.",
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertFileDoesNotExist(
            $this->directory . '/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json',
        );
    }

    public function testARecordThatCannotBeSecuredIsRefused(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        DeliveryFileDoubleState::$chmodAnswer = false;

        try {
            $fileDeliveryStore->save($this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]));
            self::fail('The unsecurable record was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store cannot secure the record'
                . " 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json'.",
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(0, $fileDeliveryStore->pendingCount());
    }

    public function testARequeueIsNotAnAdmission(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 1);
        $fileDeliveryStore->save($this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]));

        $fileDeliveryStore->requeue($this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 1], 2, 30));

        self::assertSame(2, $fileDeliveryStore->pendingCount());
    }

    public function testTakeDueAnswersOnlyTheDueInReadyAtOrder(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        $late = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 1], 1, 50);
        $early = $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 2], 1, 10);
        $fileDeliveryStore->save($late, $early);

        self::assertSame([], $fileDeliveryStore->takeDue(9));
        self::assertSame(['bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'], $this->identitiesOf($fileDeliveryStore->takeDue(10)));
        self::assertSame(
            ['bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'],
            $this->identitiesOf($fileDeliveryStore->takeDue(50)),
        );
    }

    public function testAmongEqualsTheEntryIdBreaksTheOrder(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        $later = $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 2]);
        $earlier = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 1]);
        $fileDeliveryStore->save($later, $earlier);

        self::assertSame(
            ['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'],
            $this->identitiesOf($fileDeliveryStore->takeDue(0)),
        );
    }

    public function testAnEntryTakenButNotSettledIsReTaken(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $fileDeliveryStore->save($envelope);

        self::assertSame(['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'], $this->identitiesOf($fileDeliveryStore->takeDue(0)));
        self::assertSame(['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'], $this->identitiesOf($fileDeliveryStore->takeDue(0)));
    }

    public function testSettlingRemovesTheEntryAndIsIdempotent(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $fileDeliveryStore->save($envelope);

        $fileDeliveryStore->settle($envelope);
        $fileDeliveryStore->settle($envelope);

        self::assertSame(0, $fileDeliveryStore->pendingCount());
        self::assertFileDoesNotExist(
            $this->directory . '/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json',
        );
    }

    public function testAnEntryThatCannotBeRemovedIsRefused(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $fileDeliveryStore->save($envelope);

        DeliveryFileDoubleState::$unlinkAnswer = false;

        try {
            $fileDeliveryStore->settle($envelope);
            self::fail('The unremovable entry was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store cannot remove the entry'
                . " 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARootThatCannotBeScannedIsRefused(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        DeliveryFileDoubleState::$scandirAnswer = false;

        try {
            $fileDeliveryStore->pendingCount();
            self::fail('The unscannable root was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store directory cannot be scanned.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testADeadLetterRecordLivesInTheDeadLaneWithTheStory(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12], 2, 30);

        $fileDeliveryStore->deadLetter($envelope, 'RuntimeException', 'the audit exploded', 99);

        $deadPath = $this->directory
            . '/dead/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json';

        self::assertFileExists($deadPath);
        self::assertSame(0o600, fileperms($deadPath) & 0o777);
        self::assertSame(0o700, fileperms($this->directory . '/dead') & 0o777);

        $record = json_decode((string) file_get_contents($deadPath), true);

        self::assertIsArray($record);
        self::assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $record['entry_id']);
        self::assertSame('order.paid', $record['event_name']);
        self::assertSame(['amount' => 12], $record['values']);
        self::assertSame('app.orders.audit', $record['listener_identity']);
        self::assertSame(2, $record['attempt']);
        self::assertSame(30, $record['ready_at']);
        self::assertSame('RuntimeException', $record['failure_class']);
        self::assertSame('the audit exploded', $record['failure_message']);
        self::assertSame(99, $record['dead_at']);
    }

    public function testADeadLaneThatCannotBeCreatedIsRefused(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        DeliveryFileDoubleState::$mkdirAnswer = false;

        try {
            $fileDeliveryStore->deadLetter(
                $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]),
                'RuntimeException',
                'the audit exploded',
                99,
            );
            self::fail('The uncreatable dead lane was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store cannot create its dead-letter lane.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheSameDeadLetterRewritesTheSameRecord(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);

        $fileDeliveryStore->deadLetter($envelope, 'RuntimeException', 'first', 99);
        $fileDeliveryStore->deadLetter($envelope, 'RuntimeException', 'second', 100);

        $records = glob($this->directory . '/dead/*.json');

        self::assertIsArray($records);
        self::assertCount(1, $records);

        $record = json_decode((string) file_get_contents($records[0]), true);

        self::assertIsArray($record);
        self::assertSame('second', $record['failure_message']);
        self::assertSame(100, $record['dead_at']);
    }

    public function testARecordThatCannotBeReadIsRefusedByName(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);

        file_put_contents(
            $this->directory . '/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json',
            'anything',
        );

        DeliveryFileDoubleState::$fileGetContentsAnswer = false;

        try {
            $fileDeliveryStore->takeDue(0);
            self::fail('The unreadable record was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery entry file'
                . " 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json' cannot be read.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testACorruptEntryFileRefusesTheDrainByName(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);

        file_put_contents(
            $this->directory . '/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json',
            '{corrupt',
        );

        try {
            $fileDeliveryStore->takeDue(0);
            self::fail('The corrupt entry file was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringStartsWith(
                'The delivery entry file'
                . " 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json' is refused: The"
                . ' durable delivery record is not valid JSON:',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAHandEditedEntryFileRefusesTheDrainWithTheDefect(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $fileDeliveryStore->save($envelope);

        $path = $this->directory . '/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json';
        $record = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($record);
        $record['mystery'] = 'key';
        file_put_contents($path, (string) json_encode($record));

        try {
            $fileDeliveryStore->takeDue(0);
            self::fail('The hand-edited entry file was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery entry file'
                . " 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json' is refused: The"
                . " durable delivery record carries an unknown key 'mystery'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testDotFilesAndNonRecordEntriesStayInvisibleToTheScan(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);

        file_put_contents($this->directory . '/.tmp.aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.json', '{}');
        file_put_contents($this->directory . '/operator-notes.txt', 'sweep me');
        mkdir($this->directory . '/weird.json', 0o700);

        self::assertSame(0, $fileDeliveryStore->pendingCount());
        self::assertSame([], $fileDeliveryStore->takeDue(0));
    }

    public function testTheRoundTripOverTheWholeStoreSurvives(): void
    {
        $fileDeliveryStore = new FileDeliveryStore($this->directory, 8);
        $envelope = $this->envelope(
            'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            ['amount' => 12, 'note' => 'Zalążek', 'ratio' => 1.0, 'tags' => ['a', 'b']],
            2,
            \PHP_INT_MAX,
        );

        $fileDeliveryStore->save($envelope);

        $decoded = $fileDeliveryStore->takeDue(\PHP_INT_MAX);

        self::assertCount(1, $decoded);
        $this->assertEnvelopeMatches($envelope, $decoded[0]);
        self::assertSame(1.0, $decoded[0]->values()['ratio']);
        self::assertSame(\PHP_INT_MAX, $decoded[0]->readyAt());
    }

    /**
     * @param array<string, null|array<mixed>|bool|float|int|string> $values
     */
    private function envelope(
        string $entryId,
        array $values,
        int $attempt = 1,
        int $readyAt = 0,
    ): DeliveryEnvelope {
        return new DeliveryEnvelope(
            new DeliveryEntryId($entryId),
            new EventName('order.paid'),
            $values,
            new ListenerIdentity('app.orders.audit'),
            $attempt,
            $readyAt,
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

    /**
     * The identities of the envelopes a take answered, in the take's
     * own order -- the ordering assertion's vocabulary.
     *
     * @param list<DeliveryEnvelope> $envelopes
     *
     * @return list<string>
     */
    private function identitiesOf(array $envelopes): array
    {
        $identities = [];

        foreach ($envelopes as $envelope) {
            $identities[] = $envelope->entryId()->spelling();
        }

        return $identities;
    }

    private function assertEnvelopeMatches(DeliveryEnvelope $expected, DeliveryEnvelope $actual): void
    {
        self::assertSame($expected->entryId()->spelling(), $actual->entryId()->spelling());
        self::assertSame($expected->eventName()->spelling(), $actual->eventName()->spelling());
        self::assertSame($expected->values(), $actual->values());
        self::assertSame(
            $expected->listenerIdentity()->spelling(),
            $actual->listenerIdentity()->spelling(),
        );
        self::assertSame($expected->attempt(), $actual->attempt());
        self::assertSame($expected->readyAt(), $actual->readyAt());
    }
}
