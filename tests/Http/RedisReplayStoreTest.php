<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\RedisReplayStore;
use Zef\Test\Support\Http\RecordingRedisCommands;

/**
 * The Redis replay store's claim semantics, on its own terms: the
 * INCR-atomic accounting (the count that decides, the one write the
 * winner installs), the crash-state recovery arm the sister
 * rate-limit store spells for its buckets, and the fail-closed reply
 * normalisation -- against a recording commands double (the
 * extension boundary is covered by the process-isolated commands
 * test, the same doctrine the rate-limit family's own store rides).
 *
 * @internal
 */
#[CoversClass(RedisReplayStore::class)]
final class RedisReplayStoreTest extends TestCase
{
    private const string KEY = 'zef:replay:the-replayed-id';

    public function testTheFirstSightWinsAndInstallsTheWindow(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 1;
        $recordingRedisCommands->ttl = -1;

        $redisReplayStore = new RedisReplayStore($recordingRedisCommands);

        self::assertTrue($redisReplayStore->claim(self::KEY, 300));
        self::assertSame(
            [
                ['incr', [self::KEY]],
                ['ttl', [self::KEY]],
                ['expire', [self::KEY, 300]],
            ],
            $recordingRedisCommands->calls,
            'the winner is the one INCR, and the window its single EXPIRE installs',
        );
    }

    public function testASightedIdInsideItsWindowIsTheDuplicate(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 2;
        $recordingRedisCommands->ttl = 240;

        $redisReplayStore = new RedisReplayStore($recordingRedisCommands);

        self::assertFalse($redisReplayStore->claim(self::KEY, 300));
        self::assertSame(
            [
                ['incr', [self::KEY]],
                ['ttl', [self::KEY]],
            ],
            $recordingRedisCommands->calls,
            'a duplicate pays no expire -- its window is already installed',
        );
    }

    /**
     * A TTL of exactly zero is an in-window sight, never a lost one:
     * the recovery arm reads the negative alone (the lost install the
     * phpredis contract spells -1 and -2), so a key whose window is
     * expiring this instant keeps the duplicate verdict and pays no
     * second EXPIRE -- the boundary the in-window pin holds.
     */
    public function testASightWithATtlOfExactlyZeroIsInWindow(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 2;
        $recordingRedisCommands->ttl = 0;

        $redisReplayStore = new RedisReplayStore($recordingRedisCommands);

        self::assertFalse($redisReplayStore->claim(self::KEY, 300));
        self::assertSame(
            [
                ['incr', [self::KEY]],
                ['ttl', [self::KEY]],
            ],
            $recordingRedisCommands->calls,
            'a zero TTL is the window expiring, never a lost install the recovery arm re-installs',
        );
    }

    public function testASightThatLostItsTtlIsRecoveredWhole(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 2;
        $recordingRedisCommands->ttl = -1;

        $redisReplayStore = new RedisReplayStore($recordingRedisCommands);

        self::assertFalse($redisReplayStore->claim(self::KEY, 300));
        self::assertSame(
            [
                ['incr', [self::KEY]],
                ['ttl', [self::KEY]],
                ['expire', [self::KEY, 300]],
            ],
            $recordingRedisCommands->calls,
            'the crash state between INCR and EXPIRE has its window re-installed whole',
        );
    }

    public function testABrandNewSightOverAPhantomTtlInstallsTheWindow(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 1;
        $recordingRedisCommands->ttl = -2;

        $redisReplayStore = new RedisReplayStore($recordingRedisCommands);

        self::assertTrue($redisReplayStore->claim(self::KEY, 300));
        self::assertContains(
            ['expire', [self::KEY, 300]],
            $recordingRedisCommands->calls,
            'a brand-new sight over a phantom TTL still installs its window',
        );
    }

    public function testANumericStringIncrReplyNormalises(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = '1';

        $redisReplayStore = new RedisReplayStore($recordingRedisCommands);

        self::assertTrue(
            $redisReplayStore->claim(self::KEY, 300),
            'the numeric strings some reply options carry fold to the integers they spell',
        );
    }

    public function testACorruptIncrReplyFailsClosed(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 'not a count';

        $redisReplayStore = new RedisReplayStore($recordingRedisCommands);

        try {
            $redisReplayStore->claim(self::KEY, 300);
            self::fail('A corrupt INCR reply must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'Redis replay store: INCR returned an unexpected value.',
                $httpException->getMessage(),
            );
        }
    }

    public function testATtlReplyOfFalseFailsClosed(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 1;
        $recordingRedisCommands->ttl = false;

        $redisReplayStore = new RedisReplayStore($recordingRedisCommands);

        try {
            $redisReplayStore->claim(self::KEY, 300);
            self::fail('A TTL reply of false must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'Redis replay store: TTL returned an unexpected value.',
                $httpException->getMessage(),
            );
        }
    }
}
