<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Whether a GitHub GraphQL `resolveReviewThread` mutation response reports
 * success.
 *
 * A mutation response is a success only when it carries no `errors` array
 * (see {@see GraphQlErrors}) and reports the thread as resolved. Anything
 * else -- a transport error, a permission error, a malformed body, a
 * non-array body -- is a failure the caller must surface rather than
 * swallow.
 *
 * Every branch of the verdict is observable through the published tests: an
 * errors array always wins over an otherwise-successful data section, and
 * the path walk below the guard treats every malformed or missing level --
 * including a missing `isResolved` flag -- as a failure rather than as an
 * implicit success.
 */
final readonly class ReviewThreadMutation
{
    private const array RESOLVED_FLAG_PATH = ['data', 'resolveReviewThread', 'thread', 'isResolved'];

    public static function succeeded(string $response): bool
    {
        $decoded = json_decode($response, true);

        if (!\is_array($decoded) || GraphQlErrors::present($decoded)) {
            return false;
        }

        return self::resolved($decoded, self::RESOLVED_FLAG_PATH);
    }

    /**
     * The resolved flag's own walk, one level per fold: each level is
     * judged as the map it must be before the member crosses to the
     * next level -- the decoded answer's mixedness flows through the
     * fold's own parameters, never a binding.
     *
     * @param mixed        $node the level the walk has reached
     * @param list<string> $path the levels still to walk
     */
    private static function resolved(mixed $node, array $path): bool
    {
        if ($path === []) {
            return $node === true;
        }

        $key = $path[0];

        if (!\is_array($node) || !\array_key_exists($key, $node)) {
            return false;
        }

        return self::resolved($node[$key], \array_slice($path, 1));
    }
}
