<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Whether a decoded GitHub GraphQL response carries an `errors` array.
 *
 * GraphQL reports failures in a top-level `errors` array; an empty array or
 * a malformed non-array value is not an error. Anything else -- a transport
 * error, a permission error -- is.
 */
final readonly class GraphQlErrors
{
    /**
     * @param array<array-key, mixed> $decoded
     */
    public static function present(array $decoded): bool
    {
        return self::errors($decoded['errors'] ?? null);
    }

    /**
     * The errors member's own judgement: the decoded member crosses
     * straight into the fold's mixed parameter, and only a non-empty
     * array answers present.
     *
     * @param mixed $errors the decoded errors member, as json_decode answered it
     */
    private static function errors(mixed $errors): bool
    {
        return \is_array($errors) && $errors !== [];
    }
}
