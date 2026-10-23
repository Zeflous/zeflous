<?php

declare(strict_types=1);

namespace Zef\Framework;

/**
 * The framework's environment reader: the `APP_ENV` allowlist fold
 * both environment doctrines consult -- the error lane's factories
 * ({@see Middleware\ErrorMappingFactory}, {@see
 * Middleware\DebugToolbarFactory}, {@see
 * Middleware\ProcessErrorBoundaryFactory}, and the route-debug seam's
 * own reader) and the container's mode verdict
 * ({@see ContainerMode}).
 *
 * The vocabulary is declared exactly once, here: the middleware seam
 * re-declared its own copy at the port (three factories, each with the
 * allowlist spelled inline) until the round-3 documentation-alignment
 * audit collapsed them onto this fold -- one doctrine, one spelling,
 * no drift surface. The fold is fail-closed: an absent variable -- or
 * any value the allowlist does not name -- answers *not* development,
 * so no environment spelling can accidentally arm a development
 * posture production never asked for.
 */
final readonly class AppEnvironment
{
    /**
     * The `APP_ENV` spellings that answer development: the development
     * doctrine's allowlist.
     */
    private const array DEVELOPMENT_ENVIRONMENTS = ['local', 'development', 'dev', 'test', 'testing'];

    /**
     * Whether the running environment is a development one: `APP_ENV`
     * carrying any allowlisted spelling, case-insensitive -- and
     * nothing else, an unset variable included.
     */
    public static function isDevelopment(): bool
    {
        $environment = getenv('APP_ENV');

        return \is_string($environment)
            && \in_array(mb_strtolower($environment), self::DEVELOPMENT_ENVIRONMENTS, true);
    }
}
