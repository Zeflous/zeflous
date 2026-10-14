<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The bucket table's room: the admission decision a table at capacity
 * makes for the key about to be accounted -- existing keys always keep
 * theirs, a new key reclaims the expired buckets first (only the buckets
 * whose reset epoch is still ahead of the caller's clock survive the
 * filter), and an unreclaimable capacity is the fail-closed refusal.
 *
 * Pure over the table it is handed (a copy-on-write array in, the
 * admitted table out), so the table's own accounting stays three plain
 * statements on either side of the admission.
 */
final class BucketRoom
{
    /**
     * Answers the table admitted for the key's accounting.
     *
     * @param array<string, array{count:int, reset:int}> $buckets  the table
     * @param string                                     $key      the identity
     *                                                             about to be
     *                                                             accounted
     * @param int                                        $capacity the
     *                                                             table's capacity
     *                                                             bound
     * @param int                                        $now      the caller's
     *                                                             epoch, Unix seconds
     *
     * @return array<string, array{count:int, reset:int}>
     *
     * @throws HttpException when a new key arrives at an unreclaimable
     *                       capacity
     */
    public static function admitting(array $buckets, string $key, int $capacity, int $now): array
    {
        if (isset($buckets[$key]) || \count($buckets) < $capacity) {
            return $buckets;
        }

        $buckets = array_filter($buckets, static fn (array $bucket): bool => $bucket['reset'] > $now);

        if (\count($buckets) >= $capacity) {
            throw new HttpException('Rate limiter capacity exhausted.');
        }

        return $buckets;
    }
}
