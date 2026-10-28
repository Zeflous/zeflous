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
use Zef\Framework\Adapters\Redis\RespReplyListShape;
use Zef\Framework\Adapters\Redis\RespReplyRefusal;
use Zef\Framework\Adapters\Redis\RespReplyShape;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryEnvelopeEncoder;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\ListenerIdentity;
use Zef\Test\Support\Adapters\Redis\RespSocketDoubleState;

/**
 * Pins the Redis answer to the persistence port over the socket
 * boundary's scripted doubles: the command stream every verb issues
 * -- the record written before the index member, the claim's NX PX
 * spelling with the visibility timeout in milliseconds, the settle's
 * record-before-member order, the dead record keyed by identity --
 * together with the port's own behaviours: the capacity
 * backpressure with the counts spelled, the mid-write compensation
 * whole with its stranded count, the claim arms (won, foreign,
 * re-taken by its own drainer, expired), the zombie member a
 * crashed settle left completed quietly, the fail-closed decode
 * naming the entry, and every wrong-shape reply refused with both
 * shapes named.
 *
 * @internal
 */
#[CoversClass(RedisDeliveryAdmission::class)]
#[CoversClass(RedisDeliveryClaim::class)]
#[CoversClass(RedisDeliveryClaimedRecords::class)]
#[CoversClass(RedisDeliveryKeys::class)]
#[CoversClass(RedisDeliveryStore::class)]
#[CoversClass(RespReplyListShape::class)]
#[CoversClass(RespReplyRefusal::class)]
#[CoversClass(RespReplyShape::class)]
final class RedisDeliveryStoreTest extends TestCase
{
    private DeliveryEnvelopeEncoder $deliveryEnvelopeEncoder;

    #[Override]
    protected function setUp(): void
    {
        RespSocketDoubleState::reset();
        RespSocketDoubleState::$connectAnswer = fopen('php://memory', 'r+b');
        RespSocketDoubleState::$readChunks = [];
        $this->deliveryEnvelopeEncoder = new DeliveryEnvelopeEncoder();
    }

    #[Override]
    protected function tearDown(): void
    {
        RespSocketDoubleState::reset();
    }

    public function testACapacityBelowOneIsRefused(): void
    {
        $this->expectConstructionRefusal(
            "A delivery store's capacity must be at least one, got 0.",
            0,
        );
    }

    public function testAVisibilityTimeoutBelowOneSecondIsRefused(): void
    {
        $this->expectConstructionRefusal(
            "A delivery claim's visibility timeout must be at least one second, got 0.",
            8,
            0,
        );
    }

    public function testAnEmptyPrefixIsRefused(): void
    {
        $this->expectConstructionRefusal(
            "A Redis delivery store's key prefix must be one to one hundred twenty-eight"
            . " printable ASCII characters without spaces, got ''.",
            8,
            30,
            '',
        );
    }

    public function testAPrefixCarryingASpaceIsRefused(): void
    {
        $this->expectConstructionRefusal(
            "A Redis delivery store's key prefix must be one to one hundred twenty-eight"
            . " printable ASCII characters without spaces, got 'zef bus'.",
            8,
            30,
            'zef bus',
        );
    }

    public function testAPrefixBeyondOneHundredTwentyEightCharactersIsRefused(): void
    {
        $this->expectConstructionRefusal(
            "A Redis delivery store's key prefix must be one to one hundred twenty-eight"
            . " printable ASCII characters without spaces, got '" . str_repeat('x', 129) . "'.",
            8,
            30,
            str_repeat('x', 129),
        );
    }

    public function testASavedBatchWritesEachRecordBeforeItsIndexMember(): void
    {
        $first = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $second = $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 14], 1, 50);

        $this->replies(':0', '+OK', ':1', '+OK', ':1');

        $this->store()->save($first, $second);

        self::assertSame(
            [
                $this->wire('ZCARD', 'zef:test:pending'),
                $this->wire(
                    'SET',
                    'zef:test:entry:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    $this->deliveryEnvelopeEncoder->encode($first),
                ),
                $this->wire(
                    'ZADD',
                    'zef:test:pending',
                    0,
                    'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                ),
                $this->wire(
                    'SET',
                    'zef:test:entry:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                    $this->deliveryEnvelopeEncoder->encode($second),
                ),
                $this->wire(
                    'ZADD',
                    'zef:test:pending',
                    50,
                    'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                ),
            ],
            RespSocketDoubleState::$writtenCommands,
        );
    }

    public function testAFullStoreRefusesTheBatchWholeWithTheCountsSpelled(): void
    {
        $this->replies(':8');

        try {
            $this->store(capacity: 8)->save(
                $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]),
                $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 14]),
            );
            self::fail('The full store was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The delivery store is full: capacity 8, occupied 8, demanded 2'
                . ' -- drain before deferring more.',
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(
            [$this->wire('ZCARD', 'zef:test:pending')],
            RespSocketDoubleState::$writtenCommands,
            'The refused batch wrote anything at all.',
        );
    }

    public function testARefusedMidWriteBatchIsCompensatedWhole(): void
    {
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $second = $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 14]);

        $this->replies(
            ':0',
            '+OK',
            ':1',
            '-ERR the server is full',
            ':1',
            ':1',
            ':0',
            ':0',
        );

        try {
            $this->store()->save($envelope, $second);
            self::fail('The refused batch was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The Redis server refused the command: ERR the server is full',
                $invalidArgumentException->getMessage(),
            );
        }

        self::assertSame(
            [
                $this->wire('ZCARD', 'zef:test:pending'),
                $this->wire(
                    'SET',
                    'zef:test:entry:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    $this->deliveryEnvelopeEncoder->encode($envelope),
                ),
                $this->wire(
                    'ZADD',
                    'zef:test:pending',
                    0,
                    'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                ),
                $this->wire(
                    'SET',
                    'zef:test:entry:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                    $this->deliveryEnvelopeEncoder->encode($second),
                ),
                $this->wire(
                    'DEL',
                    'zef:test:entry:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                ),
                $this->wire(
                    'ZREM',
                    'zef:test:pending',
                    'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                ),
                $this->wire(
                    'DEL',
                    'zef:test:entry:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                ),
                $this->wire(
                    'ZREM',
                    'zef:test:pending',
                    'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                ),
            ],
            RespSocketDoubleState::$writtenCommands,
            'The compensation did not remove both halves of every begun entry, record before member.',
        );
    }

    public function testAnUncompensableBatchNamesItsStrandedCount(): void
    {
        $this->replies(
            ':0',
            '+OK',
            ':1',
            '-ERR the write refused',
            ':1',
            ':1',
            '-ERR the removal refused',
            ':0',
        );

        try {
            $this->store()->save(
                $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]),
                $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 14]),
            );
            self::fail('The refused batch was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The refused durable delivery batch could not be compensated'
                . ' whole: 1 begun entries stay in the store and will drain'
                . ' -- remove them deliberately, or let the drain answer them.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnEmptyDueRangeAnswersNothingAndAsksNothing(): void
    {
        $this->replies('*0');

        self::assertSame([], $this->store()->takeDue(100));

        self::assertSame(
            [$this->wire('ZRANGEBYSCORE', 'zef:test:pending', '-inf', 100)],
            RespSocketDoubleState::$writtenCommands,
        );
    }

    public function testTheDueEntriesAreClaimedWithTheVisibilityTimeoutSpelledAndRead(): void
    {
        $first = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $second = $this->envelope('bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', ['amount' => 14], 1, 50);

        $this->replies(
            "*2\r\n\$32\r\naaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\r\n\$32\r\nbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb\r\n",
            '+OK',
            '+OK',
            "*2\r\n\$" . mb_strlen($this->jsonOf($first), '8bit') . "\r\n" . $this->jsonOf($first)
                . "\r\n\$" . mb_strlen($this->jsonOf($second), '8bit') . "\r\n" . $this->jsonOf($second) . "\r\n",
        );

        $answered = $this->store(claimTimeoutSeconds: 30)->takeDue(100);

        self::assertSame(
            [$first->entryId()->spelling(), $second->entryId()->spelling()],
            [
                $answered[0]->entryId()->spelling(),
                $answered[1]->entryId()->spelling(),
            ],
        );
        self::assertSame(
            [
                $this->wire('ZRANGEBYSCORE', 'zef:test:pending', '-inf', 100),
                $this->claimCommand(
                    'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    $this->drainerTokenFromTheWire(),
                    30_000,
                ),
                $this->claimCommand(
                    'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                    $this->drainerTokenFromTheWire(),
                    30_000,
                ),
                $this->wire(
                    'MGET',
                    'zef:test:entry:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    'zef:test:entry:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                ),
            ],
            RespSocketDoubleState::$writtenCommands,
        );
    }

    public function testAForeignClaimIsRespectedAndSkipped(): void
    {
        $this->replies(
            "*1\r\n\$32\r\naaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\r\n",
            "\$-1\r\n",
            "\$32\r\ncccccccccccccccccccccccccccccccc\r\n",
        );

        self::assertSame([], $this->store()->takeDue(100));

        self::assertSame(
            [
                $this->wire('ZRANGEBYSCORE', 'zef:test:pending', '-inf', 100),
                $this->claimCommand(
                    'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    $this->drainerTokenFromTheWire(),
                    5_000,
                ),
                $this->wire('GET', 'zef:test:claim:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
            ],
            RespSocketDoubleState::$writtenCommands,
            'A foreign claim was not respected, or its holder was not asked.',
        );
    }

    public function testAClaimThisDrainerAlreadyHoldsIsRetakenByTheNextPass(): void
    {
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);
        $dueList = "*1\r\n\$32\r\naaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\r\n";
        $records = "*1\r\n\$" . mb_strlen($this->jsonOf($envelope), '8bit') . "\r\n"
            . $this->jsonOf($envelope) . "\r\n";

        $store = $this->store();
        $this->replies($dueList, '+OK', $records);

        $firstPass = $store->takeDue(100);

        self::assertSame(
            [$envelope->entryId()->spelling()],
            [$firstPass[0]->entryId()->spelling()],
        );

        $drainerToken = $this->drainerTokenFromTheWire();
        $this->replies($dueList, "\$-1\r\n", "\$32\r\n" . $drainerToken . "\r\n", $records);

        $secondPass = $store->takeDue(100);

        self::assertSame(
            [$envelope->entryId()->spelling()],
            [$secondPass[0]->entryId()->spelling()],
            'An entry taken but not settled was not re-taken by the next pass.',
        );
    }

    public function testAnExpiredClaimIsLeftForTheNextPass(): void
    {
        $this->replies(
            "*1\r\n\$32\r\naaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\r\n",
            "\$-1\r\n",
            "\$-1\r\n",
        );

        self::assertSame([], $this->store()->takeDue(100));
        self::assertSame(
            [
                $this->wire('ZRANGEBYSCORE', 'zef:test:pending', '-inf', 100),
                $this->claimCommand(
                    'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    $this->drainerTokenFromTheWire(),
                    5_000,
                ),
                $this->wire('GET', 'zef:test:claim:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
            ],
            RespSocketDoubleState::$writtenCommands,
            'An expired claim was not left for the next pass.',
        );
    }

    public function testAZombieMemberASettleCrashedIntoCompletesQuietly(): void
    {
        $this->replies(
            "*1\r\n\$32\r\naaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\r\n",
            '+OK',
            "*1\r\n\$-1\r\n",
            ':1',
        );

        self::assertSame([], $this->store()->takeDue(100));

        self::assertSame(
            [
                $this->wire('ZRANGEBYSCORE', 'zef:test:pending', '-inf', 100),
                $this->claimCommand(
                    'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    $this->drainerTokenFromTheWire(),
                    5_000,
                ),
                $this->wire('MGET', 'zef:test:entry:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
                $this->wire(
                    'ZREM',
                    'zef:test:pending',
                    'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                ),
            ],
            RespSocketDoubleState::$writtenCommands,
            'The zombie member was not completed quietly.',
        );
    }

    public function testACorruptRecordIsRefusedWithTheEntryNamedAndTheReasonCarried(): void
    {
        $this->replies(
            "*1\r\n\$32\r\naaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\r\n",
            '+OK',
            "*1\r\n\$7\r\nnotjson\r\n",
        );

        try {
            $this->store()->takeDue(100);
            self::fail('The corrupt record was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The delivery entry record 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' is refused:"
                . ' The durable delivery record is not valid JSON: Syntax error.',
                $invalidArgumentException->getMessage(),
            );
            self::assertSame(
                0,
                $invalidArgumentException->getCode(),
                "The wrapped refusal did not carry the wrap chain's own code.",
            );
            self::assertNotNull($invalidArgumentException->getPrevious());
        }
    }

    public function testAMultiReadThatDoesNotAnswerOneElementPerKeyIsRefused(): void
    {
        $this->replies(
            "*2\r\n\$32\r\naaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\r\n\$32\r\nbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb\r\n",
            '+OK',
            '+OK',
            "*1\r\n\$-1\r\n",
        );

        try {
            $this->store()->takeDue(100);
            self::fail('The short multi-read was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The MGET reply is refused: 2 entry records were asked for,'
                . ' the reply answered 1 elements.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testSettlingRemovesTheRecordBeforeTheIndexMemberAndIsIdempotent(): void
    {
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);

        $this->replies(':0', ':0');

        $this->store()->settle($envelope);

        self::assertSame(
            [
                $this->wire('DEL', 'zef:test:entry:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
                $this->wire('ZREM', 'zef:test:pending', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
            ],
            RespSocketDoubleState::$writtenCommands,
        );
    }

    public function testARequeueWritesTheRetrysRecordBeforeItsIndexMember(): void
    {
        $envelope = $this->envelope(
            'cccccccccccccccccccccccccccccccc',
            ['amount' => 12],
            2,
            150,
        );

        $this->replies('+OK', ':1');

        $this->store()->requeue($envelope);

        self::assertSame(
            [
                $this->wire(
                    'SET',
                    'zef:test:entry:cccccccccccccccccccccccccccccccc',
                    $this->deliveryEnvelopeEncoder->encode($envelope),
                ),
                $this->wire(
                    'ZADD',
                    'zef:test:pending',
                    150,
                    'cccccccccccccccccccccccccccccccc',
                ),
            ],
            RespSocketDoubleState::$writtenCommands,
        );
    }

    public function testADeadLetterIsOneRecordKeyedByTheSpentEntrysIdentity(): void
    {
        $envelope = $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]);

        $this->replies('+OK');

        $this->store()->deadLetter($envelope, 'RuntimeException', 'the listener refused', 200);

        self::assertSame(
            [
                $this->wire(
                    'SET',
                    'zef:test:dead:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    $this->deliveryEnvelopeEncoder->encodeDeadLetter(
                        $envelope,
                        'RuntimeException',
                        'the listener refused',
                        200,
                    ),
                ),
            ],
            RespSocketDoubleState::$writtenCommands,
        );
    }

    public function testThePendingCountIsTheIndexsOwnCount(): void
    {
        $this->replies(':3');

        self::assertSame(3, $this->store()->pendingCount());

        self::assertSame(
            [$this->wire('ZCARD', 'zef:test:pending')],
            RespSocketDoubleState::$writtenCommands,
        );
    }

    public function testACounterReplyOfTheWrongShapeIsRefusedWithBothShapesNamed(): void
    {
        $this->replies('+OK');

        try {
            $this->store()->pendingCount();
            self::fail('The status counter reply was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply for 'ZCARD' is refused: the command answers an integer,"
                . " the reply decoded into the string 'OK'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAStatusReplyOfTheWrongShapeIsRefusedWithBothShapesNamed(): void
    {
        $this->replies(':1');

        try {
            $this->store()->deadLetter(
                $this->envelope('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', ['amount' => 12]),
                'RuntimeException',
                'the listener refused',
                200,
            );
            self::fail('The integer status reply was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply for 'SET' is refused: the command answers a status string,"
                . ' the reply decoded into the integer 1.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testADueRangeReplyOfTheWrongShapeIsRefused(): void
    {
        $this->replies(':0');

        try {
            $this->store()->takeDue(100);
            self::fail('The integer range reply was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The Redis reply for 'ZRANGEBYSCORE' is refused: the command answers an array"
                . ' of bulk strings, the reply decoded into the integer 0.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    private function store(
        int $capacity = 8,
        int $claimTimeoutSeconds = 5,
        string $prefix = 'zef:test',
    ): RedisDeliveryStore {
        return new RedisDeliveryStore(
            new RespConnection('127.0.0.1', 6379, 5.0),
            $capacity,
            $claimTimeoutSeconds,
            $prefix,
        );
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
     * Scripts the replies the scripted wire will answer, one chunk
     * per reply -- the grammar's own line ending appended to a
     * hand-spelled chunk that left it off.
     */
    private function replies(string ...$replyChunks): void
    {
        $chunks = [];

        foreach ($replyChunks as $replyChunk) {
            $chunks[] = str_ends_with($replyChunk, "\r\n")
                ? $replyChunk
                : $replyChunk . "\r\n";
        }

        RespSocketDoubleState::$readChunks = $chunks;
    }

    /**
     * One command's wire bytes, spelled by the test's own hand --
     * the oracle the recorded stream is asserted against.
     */
    private function wire(int|string ...$arguments): string
    {
        $bytes = '*' . \count($arguments) . "\r\n";

        foreach ($arguments as $argument) {
            $spelling = (string) $argument;

            $bytes .= '$' . mb_strlen($spelling, '8bit') . "\r\n" . $spelling . "\r\n";
        }

        return $bytes;
    }

    /**
     * The claim command's own bytes: the entry's claim key, the
     * drainer's token, and the NX PX spelling with the visibility
     * timeout in milliseconds.
     */
    private function claimCommand(string $entryId, string $drainerToken, int $claimTimeoutMs): string
    {
        return $this->wire(
            'SET',
            'zef:test:claim:' . $entryId,
            $drainerToken,
            'NX',
            'PX',
            $claimTimeoutMs,
        );
    }

    /**
     * The drainer's token as the wire recorded it -- the only honest
     * way a test can know a spelling the store minted privately.
     */
    private function drainerTokenFromTheWire(): string
    {
        $token = '';

        foreach (RespSocketDoubleState::$writtenCommands as $command) {
            if (preg_match('/claim:[0-9a-f]{32}\r\n\$32\r\n([0-9a-f]{32})\r\n/', $command, $match) !== 1) {
                continue;
            }

            $token = $match[1];
        }

        if ($token === '') {
            self::fail('The drainer token was never carried on the wire.');
        }

        return $token;
    }

    private function jsonOf(DeliveryEnvelope $deliveryEnvelope): string
    {
        return $this->deliveryEnvelopeEncoder->encode($deliveryEnvelope);
    }

    private function expectConstructionRefusal(
        string $message,
        int $capacity,
        int $claimTimeoutSeconds = 5,
        string $prefix = 'zef:test',
    ): void {
        try {
            $this->store($capacity, $claimTimeoutSeconds, $prefix);
            self::fail('The invalid construction was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame($message, $invalidArgumentException->getMessage());
        }
    }
}
