<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The `reviewThreads.totalCount` figure inside a GitHub GraphQL payload.
 *
 * The connection's own total is the one honest truncation witness the
 * resolver can cross-check its walk against: the pages a cursor walk
 * visited -- usable threads and unusable nodes alike -- must add up to
 * exactly the total the connection reports, and a walk that walked fewer
 * than the total is a walk whose `pageInfo` lied (`hasNextPage: false`
 * over a connection that still holds threads), which would otherwise
 * leave a blocked pull request silently unresolved while the sweep
 * reports success.
 *
 * The walk narrows every `mixed` json_decode() level and refuses a
 * payload without the shape -- a missing or non-integer total is a
 * refusal, never a zero: a total the resolver cannot read is a total it
 * cannot cross-check, and the fail-closed direction is to stop rather
 * than to trust the pages alone.
 */
final readonly class ReviewThreadTotalCount
{
    private const string MISSING_TOTAL = 'Review-thread payload is missing reviewThreads.totalCount.';

    private const array CONNECTION_PATH = ['data', 'repository', 'pullRequest', 'reviewThreads'];

    public function __construct(private string $json)
    {
    }

    /**
     * The connection's own thread total, narrowed from `mixed` and
     * refused when the payload did not carry the integer shape.
     */
    public function value(): int
    {
        $decoded = json_decode($this->json, true);

        if (!\is_array($decoded)) {
            throw new ToolingException(self::MISSING_TOTAL);
        }

        $value = $decoded;

        foreach (self::CONNECTION_PATH as $key) {
            $next = $value[$key] ?? null;

            if (!\is_array($next)) {
                throw new ToolingException(self::MISSING_TOTAL);
            }

            $value = $next;
        }

        $total = $value['totalCount'] ?? null;

        if (!\is_int($total)) {
            throw new ToolingException(self::MISSING_TOTAL);
        }

        return $total;
    }
}
