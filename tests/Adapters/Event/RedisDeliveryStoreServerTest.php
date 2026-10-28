<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\Event;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Event\RedisDeliveryAdmission;
use Zef\Framework\Adapters\Event\RedisDeliveryClaim;
use Zef\Framework\Adapters\Event\RedisDeliveryClaimedRecords;
use Zef\Framework\Adapters\Event\RedisDeliveryKeys;
use Zef\Framework\Adapters\Event\RedisDeliveryStore;
use Zef\Framework\Adapters\Redis\RespConnection;
use Zef\Framework\Adapters\Redis\RespReplyShape;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\ListenerIdentity;
use Zef\Test\Support\Adapters\Redis\RedisMockServer;
use Zef\Test\Support\Adapters\Redis\RespSocketDoubleState;

/**
 * Pins the Redis answer to the persistence port against the real
 * mock server: the port's whole protocol over a shared server two
 * connections observe -- the due ordering the sorted set's own
 * (score, member) order answers, the cross-process exclusivity the
 * file adapter declared absent (a second store's takeDue respects
 * the first store's claims), the re-take of a claim its own drainer
 * already holds, the visibility timeout that hands a crashed
 * drainer's entries to the next one, the zombie member a crashed
 * settle left completed quietly, and the dead-letter record an
 * operator reads, rewritten never doubled. Each test names its own
 * key prefix -- one server, one state, every test its own namespace
 * inside it, the same isolation discipline the deployment's own
 * prefixes spell.
 *
 * @internal
 */
#[CoversClass(RedisDeliveryAdmission::class)]
#[CoversClass(RedisDeliveryClaim::class)]
#[CoversClass(RedisDeliveryClaimedRecords::class)]
#[CoversClass(RedisDeliveryKeys::class)]
#[CoversClass(RedisDeliveryStore::class)]
#[CoversClass(RespReplyShape::class)]
final class RedisDeliveryStoreServerTest extends TestCase
{
    private static RedisMockServer $redisMockServer;

    private string $prefix;

    #[Override]
    public static function setUpBeforeClass(): void
    {
        self::$redisMockServer = new RedisMockServer();
        self::$redisMockServer->start();
    }

    #[Override]
    public static function tearDownAfterClass(): void
    {
        self::$redisMockServer->stop();
    }

    #[Override]
    protected function setUp(): void
    {
        RespSocketDoubleState::reset();
        $this->prefix = 'zef:test:' . bin2hex(random_bytes(6));
    }

    #[Override]
    protected function tearDown(): void
    {
        RespSocketDoubleState::reset();
    }

    public function testThePortsWholeProtocolRoundTripsOverTheSharedServer(): void
    {
        $store = $this->store();
        $first = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $second = $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 14], 1, 50);

        $store->save($first, $second);
        self::assertSame(2, $store->pendingCount());

        $answered = $store->takeDue(100);
        self::assertSame(
            [
                $first->entryId()->spelling(),
                $second->entryId()->spelling(),
            ],
            [
                $answered[0]->entryId()->spelling(),
                $answered[1]->entryId()->spelling(),
            ],
        );
        self::assertSame(2, $store->pendingCount());

        $store->settle($first, $second);
        self::assertSame(0, $store->pendingCount());
        self::assertSame([], $store->takeDue(100));
    }

    public function testTheDueAnswerCarriesTheIndexsOwnOrder(): void
    {
        $store = $this->store();
        $late = $this->envelope('ffffffffffffffffffffffffffffffff', ['amount' => 1], 1, 200);
        $early = $this->envelope('eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee', ['amount' => 2], 1, 50);
        $tieBefore = $this->envelope('11111111111111111111111111111111', ['amount' => 3], 1, 100);
        $tieAfter = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 4], 1, 100);

        $store->save($late, $early, $tieBefore, $tieAfter);

        $answered = $store->takeDue(200);

        self::assertSame(
            [
                $early->entryId()->spelling(),
                $tieBefore->entryId()->spelling(),
                $tieAfter->entryId()->spelling(),
                $late->entryId()->spelling(),
            ],
            [
                $answered[0]->entryId()->spelling(),
                $answered[1]->entryId()->spelling(),
                $answered[2]->entryId()->spelling(),
                $answered[3]->entryId()->spelling(),
            ],
            'The due answer did not carry ready-at first, entry identity second.',
        );
    }

    public function testASecondStoreRespectsTheFirstStoresClaims(): void
    {
        $first = $this->store();
        $second = $this->store();
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);

        $first->save($envelope);

        $takenByTheFirst = $first->takeDue(100);
        $takenByTheSecond = $second->takeDue(100);

        self::assertSame(
            [$envelope->entryId()->spelling()],
            [$takenByTheFirst[0]->entryId()->spelling()],
        );
        self::assertSame(
            [],
            $takenByTheSecond,
            "The second store did not respect the first store's claim -- the exclusivity"
                . ' this adapter exists to answer.',
        );
        self::assertSame(1, $second->pendingCount());
    }

    public function testASettledEntryIsGoneForEveryStore(): void
    {
        $first = $this->store();
        $second = $this->store();
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);

        $first->save($envelope);
        $taken = $first->takeDue(100);
        $first->settle($envelope);

        self::assertSame([], $second->takeDue(100));
        self::assertSame(0, $second->pendingCount());
        self::assertCount(1, $taken);
    }

    public function testAClaimItsOwnDrainerHoldsIsRetakenByTheNextPass(): void
    {
        $store = $this->store();
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);

        $store->save($envelope);

        $firstPass = $store->takeDue(100);
        $secondPass = $store->takeDue(100);

        self::assertCount(1, $firstPass);
        self::assertSame(
            [$envelope->entryId()->spelling()],
            [$secondPass[0]->entryId()->spelling()],
            'An entry taken but not settled was not re-taken by the next pass.',
        );
    }

    public function testAnExpiredClaimHandsTheEntryToTheNextDrainer(): void
    {
        $first = $this->store(claimTimeoutSeconds: 1);
        $second = $this->store(claimTimeoutSeconds: 1);
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);

        $first->save($envelope);
        self::assertCount(1, $first->takeDue(100));

        $notTaken = $second->takeDue(100);
        self::assertSame(
            [],
            $notTaken,
            'The live claim was not respected within its visibility timeout.',
        );

        usleep(1_200_000);

        $retaken = $second->takeDue(100);

        self::assertSame(
            [$envelope->entryId()->spelling()],
            [$retaken[0]->entryId()->spelling()],
            'The expired claim did not hand the entry to the next drainer.',
        );
    }

    public function testAZombieMemberASettleCrashedIntoCompletesQuietly(): void
    {
        $store = $this->store();
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);

        $store->save($envelope);

        $operator = $this->connection();
        $operator->command('DEL', $this->prefix . ':entry:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

        self::assertSame([], $store->takeDue(100));
        self::assertSame(0, $store->pendingCount());
    }

    public function testARequeueLandsAsTheRetryItSpellsAndTheSpentEntrySettles(): void
    {
        $store = $this->store();
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $retry = $this->envelope(
            'cccccccccccccccccccccccccccccccc',
            ['amount' => 12],
            2,
            150,
        );

        $store->save($envelope);
        self::assertCount(1, $store->takeDue(100));

        $store->requeue($retry);
        $store->settle($envelope);

        self::assertSame(1, $store->pendingCount());
        self::assertSame([], $store->takeDue(100));

        $answered = $store->takeDue(150);

        self::assertSame(
            [$retry->entryId()->spelling()],
            [$answered[0]->entryId()->spelling()],
        );
    }

    public function testADeadLetterIsTheRecordAnOperatorReadsRewrittenNeverDoubled(): void
    {
        $store = $this->store();
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $operator = $this->connection();

        $store->deadLetter($envelope, 'RuntimeException', 'the first refusal', 200);

        $firstStory = $operator->command(
            'GET',
            $this->prefix . ':dead:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        );
        self::assertSame(
            200,
            $this->deadAtOf($firstStory),
            'The operator could not read the dead record the store wrote.',
        );

        $store->deadLetter($envelope, 'RuntimeException', 'the second refusal', 300);

        $secondStory = $operator->command(
            'GET',
            $this->prefix . ':dead:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        );

        self::assertSame(
            300,
            $this->deadAtOf($secondStory),
            'The same entry dead-lettered twice doubled its record instead of rewriting it.',
        );
        self::assertSame(0, $store->pendingCount());
    }

    public function testAFullStoreRefusesTheBatchWholeOverTheWire(): void
    {
        $store = $this->store(capacity: 1);
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $second = $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 14]);

        $store->save($envelope);

        try {
            $store->save($second);
            self::fail('The full store was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store is full: capacity 1, occupied 1, demanded 1'
                . ' -- drain before deferring more.',
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(1, $store->pendingCount());
    }

    private function store(
        int $capacity = 8,
        int $claimTimeoutSeconds = 5,
    ): RedisDeliveryStore {
        return new RedisDeliveryStore(
            $this->connection(),
            $capacity,
            $claimTimeoutSeconds,
            $this->prefix,
        );
    }

    private function connection(): RespConnection
    {
        return new RespConnection(self::$redisMockServer->host(), self::$redisMockServer->port(), 2.0);
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

    /**
     * The dead-at moment a dead record's own spelling carries -- the
     * story's own timestamp, read the way an operator would.
     */
    private function deadAtOf(mixed $story): int
    {
        self::assertIsString($story);

        $record = json_decode($story, true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($record);
        self::assertIsInt($record['dead_at'] ?? null);

        return $record['dead_at'];
    }
}
