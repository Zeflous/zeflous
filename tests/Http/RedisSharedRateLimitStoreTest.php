<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\RedisSharedRateLimitStore;
use Zef\Test\Support\Http\RecordingRedisCommands;

/**
 * The Redis shared store's window semantics, on its own terms: the
 * INCR-atomic accounting, the TTL-derived reset epoch, the crash
 * recovery arm, and the fail-closed reply normalisation -- against a
 * recording commands double (the extension boundary is covered by the
 * process-isolated commands test).
 *
 * @internal
 */
#[CoversClass(RedisSharedRateLimitStore::class)]
final class RedisSharedRateLimitStoreTest extends TestCase
{
    public function testABrandNewBucketInstallsTheFullWindow(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 1;
        $recordingRedisCommands->ttl = -2;

        $redisSharedRateLimitStore = new RedisSharedRateLimitStore($recordingRedisCommands);

        $bucket = $redisSharedRateLimitStore->increment('203.0.113.9', 60, 100);

        self::assertSame(['count' => 1, 'reset' => 160], $bucket);
        self::assertSame(
            [
                ['incr', ['zef:ratelimit:' . hash('sha256', '203.0.113.9')]],
                ['ttl', ['zef:ratelimit:' . hash('sha256', '203.0.113.9')]],
                ['expire', ['zef:ratelimit:' . hash('sha256', '203.0.113.9'), 60]],
            ],
            $recordingRedisCommands->calls,
            'one INCR, one TTL, and the full-window EXPIRE the new bucket installs',
        );
    }

    public function testAnExistingBucketResetsAtItsRemainingTtl(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 2;
        $recordingRedisCommands->ttl = 37;

        $redisSharedRateLimitStore = new RedisSharedRateLimitStore($recordingRedisCommands);

        $bucket = $redisSharedRateLimitStore->increment('203.0.113.9', 60, 100);

        self::assertSame(['count' => 2, 'reset' => 137], $bucket);
        self::assertNotContains(
            ['expire', ['zef:ratelimit:' . hash('sha256', '203.0.113.9'), 60]],
            $recordingRedisCommands->calls,
        );
    }

    public function testABucketThatLostItsTtlIsRecoveredWhole(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 5;
        $recordingRedisCommands->ttl = -1;

        $redisSharedRateLimitStore = new RedisSharedRateLimitStore($recordingRedisCommands);

        $bucket = $redisSharedRateLimitStore->increment('203.0.113.9', 60, 100);

        self::assertSame(['count' => 5, 'reset' => 160], $bucket);
        self::assertContains(
            ['expire', ['zef:ratelimit:' . hash('sha256', '203.0.113.9'), 60]],
            $recordingRedisCommands->calls,
        );
    }

    public function testAnExistingBucketWithZeroTtlResetsNow(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 2;
        $recordingRedisCommands->ttl = 0;

        $redisSharedRateLimitStore = new RedisSharedRateLimitStore($recordingRedisCommands);

        $bucket = $redisSharedRateLimitStore->increment('203.0.113.9', 60, 100);

        self::assertSame(['count' => 2, 'reset' => 100], $bucket, 'a zero TTL means the window resets now');
        self::assertNotContains(
            ['expire', ['zef:ratelimit:' . hash('sha256', '203.0.113.9'), 60]],
            $recordingRedisCommands->calls,
        );
    }

    public function testANumericStringIncrReplyNormalises(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = '7';
        $recordingRedisCommands->ttl = 10;

        $redisSharedRateLimitStore = new RedisSharedRateLimitStore($recordingRedisCommands);

        self::assertSame(['count' => 7, 'reset' => 110], $redisSharedRateLimitStore->increment('203.0.113.9', 60, 100));
    }

    public function testACorruptIncrReplyFailsClosed(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 'not-a-count';

        $redisSharedRateLimitStore = new RedisSharedRateLimitStore($recordingRedisCommands);

        try {
            $redisSharedRateLimitStore->increment('203.0.113.9', 60, 100);
            self::fail('A corrupt reply must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'Redis rate-limit store: INCR returned an unexpected value.',
                $httpException->getMessage(),
            );
        }
    }

    public function testATtlReplyOfFalseFailsClosed(): void
    {
        $recordingRedisCommands = new RecordingRedisCommands();
        $recordingRedisCommands->incr = 2;
        $recordingRedisCommands->ttl = false;

        $redisSharedRateLimitStore = new RedisSharedRateLimitStore($recordingRedisCommands);

        try {
            $redisSharedRateLimitStore->increment('203.0.113.9', 60, 100);
            self::fail('A corrupt reply must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'Redis rate-limit store: TTL returned an unexpected value.',
                $httpException->getMessage(),
            );
        }
    }
}
