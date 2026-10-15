<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The rate-limit family's store vocabulary: the closed set of accounting
 * topologies the `security.rate_limit.store` knob may name.
 *
 * The reading is case-insensitive (the environment's spelling folds) and
 * fail-closed: an unknown name is rejected with the value spelled out,
 * because the reference silently answered its in-memory default for any
 * typo -- a shared-store request the operator believes they made while
 * their accounting quietly stayed per-process, its effective limit
 * multiplied by the worker count. Declaring a store is a statement of
 * topology; the reading refuses what it cannot promise.
 */
final class RateLimitStores
{
    /** The per-process zero-dependency store ({@see InMemoryRateLimiter}). */
    public const string MEMORY = 'memory';

    /** The one-process-tree shared store ({@see ApcuRateLimiter}). */
    public const string APCU = 'apcu';

    /** The cross-host shared store ({@see RedisRateLimiter}). */
    public const string REDIS = 'redis';

    /**
     * Reads one store knob's value through the closed vocabulary.
     *
     * @throws HttpException when the value is not one of the known stores
     */
    public static function read(mixed $value): string
    {
        if (\is_string($value)) {
            $folded = mb_strtolower($value);

            if (\in_array($folded, [self::MEMORY, self::APCU, self::REDIS], true)) {
                return $folded;
            }

            throw new HttpException(
                'The security.rate_limit store knob must be one of the known stores '
                . "(memory, apcu, redis), '" . $value . "' given.",
            );
        }

        throw new HttpException(
            'The security.rate_limit store knob must be one of the known stores '
            . '(memory, apcu, redis), ' . get_debug_type($value) . ' given.',
        );
    }
}
