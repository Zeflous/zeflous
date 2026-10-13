<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The CORS origin allow-list as constructed: the union the middleware
 * accepts, narrowed to the entries' comparison forms.
 *
 * This is the construction half of the reference implementation's
 * constructor state, split from the judgment half ({@see CorsPolicy}) so
 * each side stays a small, single-purpose object: an entry of `'*'` arms
 * the echo-all policy (and, exactly as the reference behaved, entries
 * after a wildcard are not normalised at all -- the reference returned
 * from inside its walk the moment `'*'` was seen, which also left later
 * malformed entries silently unvalidated); any other entry is normalised
 * to its comparison form ({@see CorsOrigin}); the union narrowing itself
 * lives in {@see AllowListEntries}.
 *
 * The allow-list deduplication the reference performed
 * (`array_values(array_unique(...))`) is dropped: the judgment iterates
 * the list and answers on the first equality, so a duplicate entry is
 * indistinguishable from its removal through every read path.
 *
 * Attribution: legacy `zef-framework` `Middleware/CorsMiddleware.php`
 * (constructor), move-verbatim behaviour.
 */
final readonly class AllowedOrigins
{
    /**
     * @param list<string> $origins         the allowed origins in their comparison form
     * @param bool         $carriesWildcard whether an entry was the wildcard
     */
    public function __construct(
        public array $origins,
        public bool $carriesWildcard,
    ) {
    }

    /**
     * @param null|list<mixed>|string $allowOrigin the allow-list entries as the caller supplied them
     *
     * @throws HttpException when an entry is not a string, or a non-wildcard entry is malformed
     */
    public static function fromConfig(array|string|null $allowOrigin): self
    {
        $entries = AllowListEntries::narrowed($allowOrigin);

        if (self::carriesWildcard($entries)) {
            return new self([], true);
        }

        return new self(self::normalized($entries), false);
    }

    /**
     * @param list<string> $entries the trimmed, non-empty entries in their raw spelling
     *
     * @return list<string> the entries' comparison forms
     *
     * @throws HttpException when an entry is not a well-formed origin
     */
    private static function normalized(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $entry) {
            $normalized[] = CorsOrigin::normalize($entry);
        }

        return $normalized;
    }

    /**
     * @param list<string> $entries the trimmed, non-empty entries in their raw spelling
     */
    private static function carriesWildcard(array $entries): bool
    {
        return array_any($entries, static fn (string $entry): bool => $entry === '*');
    }
}
