<?php

declare(strict_types=1);

namespace Zef\Framework;

/**
 * The framework's environment reader: the `APP_ENV` allowlist fold
 * both environment doctrines consult -- the error lane's toolbar
 * ({@see Middleware\DebugToolbarFactory} arms it with
 * the same words) and the container's mode verdict
 * ({@see ContainerMode}).
 *
 * The vocabulary is declared once here for the container side and
 * re-declared in the middleware seam's own fold, the same
 * re-declaration trade `AotToggles` carries (the layer graph keeps
 * the seam unreachable; its KDD records the decision). The fold is
 * fail-closed: an absent variable -- or any value the allowlist does
 * not name -- answers *not* development, so no environment spelling
 * can accidentally arm a development posture production never asked
 * for.
 */
final readonly class AppEnvironment
{
    /**
     * The `APP_ENV` spellings that answer development: the toolbar
     * doctrine's own allowlist.
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
