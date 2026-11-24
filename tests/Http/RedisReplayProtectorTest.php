<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\RedisReplayProtector;
use Zef\Framework\Http\RedisReplayStore;
use Zef\Framework\Http\ReplayDecision;
use Zef\Test\Support\Http\RecordingRedisCommands;

/**
 * Pins the cross-host Redis replay protector: the verdict grammar the
 * interface owns (the null id that needs no protection, the
 * malformed id rejected, the won claim accepted, the refused claim
 * the duplicate it is -- the count the accounting, never the
 * ambiguous add the APCu spelling reads back for), the window
 * rounded up to the whole seconds Redis's own EXPIRE keeps (never
 * silently shortened), the key under the Redis family's own colon
 * prefix, and the constructor's window bound -- composed over the
 * real store on the recording commands double, the seam the whole
 * family's tests ride.
 *
 * @internal
 */
#[CoversClass(RedisReplayProtector::class)]
final class RedisReplayProtectorTest extends TestCase
{
    private const string REPLAY_ID = 'the-replayed-id';

    public function testANullReplayIdNeedsNoProtection(): void
    {
        $redisReplayProtector = $this->protector(new RecordingRedisCommands());

        self::assertSame(ReplayDecision::NOT_REQUIRED, $redisReplayProtector->check(null, 1000));
    }

    public function testAnEmptyReplayIdIsRejected(): void
    {
        $redisReplayProtector = $this->protector(new RecordingRedisCommands());

        self::assertSame(ReplayDecision::REJECTED, $redisReplayProtector->check('', 1000));
    }

    public function testAnOverBoundReplayIdIsRejected(): void
    {
        $redisReplayProtector = $this->protector(new RecordingRedisCommands());

        self::assertSame(
            ReplayDecision::REJECTED,
            $redisReplayProtector->check(str_repeat('a', 129), 1000),
            'an id above the request grammar bound never reaches a filesystem or wire call',
        );
    }

    public function testTheFirstSightIsAcceptedUnderThePrefixedKey(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 1;
        $recordingRedisCommands->ttl = -1;

        $redisReplayProtector = $this->protector($recordingRedisCommands);

        self::assertSame(ReplayDecision::ACCEPT, $redisReplayProtector->check(self::REPLAY_ID, 1000));
        self::assertSame(
            'zef:replay:' . self::REPLAY_ID,
            $recordingRedisCommands->calls[0][1][0],
            'the sight rides the Redis family colon-prefixed key',
        );
    }

    public function testASightedIdInsideTheWindowIsTheDuplicate(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 2;
        $recordingRedisCommands->ttl = 240;

        $redisReplayProtector = $this->protector($recordingRedisCommands);

        self::assertSame(ReplayDecision::DUPLICATE, $redisReplayProtector->check(self::REPLAY_ID, 1000));
    }

    /**
     * The window's round-up: a 300_000 ms window is 300 whole
     * seconds of EXPIRE, and a 300_001 ms window is 301 -- the
     * duplicate window stays at least as long as configured, never
     * silently shortened, the same arithmetic the APCu twin spells.
     */
    public function testTheWindowRoundsUpToWholeSeconds(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 1;
        $recordingRedisCommands->ttl = -1;

        $redisReplayProtector = new RedisReplayProtector(
            new RedisReplayStore($recordingRedisCommands),
            300_001,
        );

        $redisReplayProtector->check(self::REPLAY_ID, 1000);

        self::assertContains(
            ['expire', ['zef:replay:' . self::REPLAY_ID, 301]],
            $recordingRedisCommands->calls,
            'the window rounds up to the whole second the millisecond spills over',
        );
    }

    public function testAWindowBelowOneMillisecondRefusesTheConstruction(): void
    {
        try {
            new RedisReplayProtector(new RedisReplayStore(new RecordingRedisCommands()), 0);
            self::fail('A window below one millisecond must refuse the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth replay window must be 1 or above.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * The window's round-up boundary series, pinned at every edge the
     * padding can cross: 999 ms and 1 000 ms both round up to one
     * second, 1 001 ms and 1 002 ms both answer two seconds. A padding
     * one short or a divisor one off moves an edge, and the series sees
     * it -- the same series the APCu twin's own battery pins.
     */
    public function testTheWindowRoundsUpAtEveryEdgeThePaddingCanCross(): void
    {
        $edges = [
            999 => 1,
            1_000 => 1,
            1_001 => 2,
            1_002 => 2,
        ];

        foreach ($edges as $windowMs => $expectedTtl) {
            $recordingRedisCommands = new RecordingRedisCommands();
            $recordingRedisCommands->incr = 1;
            $recordingRedisCommands->ttl = -1;

            new RedisReplayProtector(
                new RedisReplayStore($recordingRedisCommands),
                $windowMs,
            )->check(self::REPLAY_ID, 1_000);

            self::assertContains(
                ['expire', ['zef:replay:' . self::REPLAY_ID, $expectedTtl]],
                $recordingRedisCommands->calls,
                \sprintf('a %d ms window installs a %d second TTL', $windowMs, $expectedTtl),
            );
        }
    }

    public function testTheDefaultWindowInstallsTheThreeHundredSecondTtl(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 1;
        $recordingRedisCommands->ttl = -1;

        $redisReplayProtector = $this->protector($recordingRedisCommands);

        self::assertSame(ReplayDecision::ACCEPT, $redisReplayProtector->check(self::REPLAY_ID, 1_000));
        self::assertContains(
            ['expire', ['zef:replay:' . self::REPLAY_ID, 300]],
            $recordingRedisCommands->calls,
            'the default five-minute window is the 300-second TTL the winner installs',
        );
    }

    public function testAOneMillisecondWindowIsAdmittedAndInstallsAOneSecondTtl(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 1;
        $recordingRedisCommands->ttl = -1;

        $redisReplayProtector = new RedisReplayProtector(
            new RedisReplayStore($recordingRedisCommands),
            1,
        );

        self::assertSame(ReplayDecision::ACCEPT, $redisReplayProtector->check(self::REPLAY_ID, 1_000));
        self::assertContains(
            ['expire', ['zef:replay:' . self::REPLAY_ID, 1]],
            $recordingRedisCommands->calls,
            'the smallest admitted window still installs a whole second',
        );
    }

    /**
     * The protector over the real store on the recording commands
     * double: the family's own injectable seam, the same doctrine the
     * rate-limit family's tests ride.
     */
    private function protector(RecordingRedisCommands $recordingRedisCommands): RedisReplayProtector
    {
        return new RedisReplayProtector(new RedisReplayStore($recordingRedisCommands), 300_000);
    }
}
