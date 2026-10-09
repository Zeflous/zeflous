<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

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
        $errors = $decoded['errors'] ?? null;

        return \is_array($errors) && $errors !== [];
    }
}
