<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

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

        $value = $decoded;

        foreach (self::RESOLVED_FLAG_PATH as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return false;
            }

            $value = $value[$key];
        }

        return $value === true;
    }
}
