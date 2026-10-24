<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The constraint pattern's compilability probe: the one question the
 * constraints fold asks before it accepts a declared pattern -- does
 * this anchored pattern compile at all?
 *
 * The probe converts the engine-level diagnostic an invalid pattern
 * raises into the probe's own answer: an invalid pattern is an
 * expected outcome here, not an error to report, so the scoped
 * handler throws a marker the catch turns into `false`. The fold
 * exists so {@see RouteConstraints} can stay a single-question class
 * under the architecture gate's difficulty ceiling -- the probe is
 * the error-handler machinery that question needs, nothing more.
 *
 * @internal consumed through {@see RouteConstraints}
 */
final readonly class RouteConstraintCompilability
{
    /**
     * Whether the anchored pattern compiles.
     *
     * @param non-empty-string $anchored the fully anchored constraint pattern
     */
    public static function probe(string $anchored): bool
    {
        set_error_handler(static function (): never {
            throw new ConfigException('The constraint pattern does not compile.');
        });

        try {
            return preg_match($anchored, '') !== false;
        } catch (ConfigException) {
            return false;
        } finally {
            restore_error_handler();
        }
    }
}
