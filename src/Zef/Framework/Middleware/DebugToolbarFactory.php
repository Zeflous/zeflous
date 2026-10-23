<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\AppEnvironment;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/** Arms the toolbar only for explicit debug mode in a non-production environment.
 *
 * @api the framework profile's `http.debug_toolbar` service registration
 *      names this factory through the container's string-addressed grammar
 *      (`FrameworkDefaults::profile()`), so the analyser cannot see the
 *      call -- the same `@api` posture every sibling factory carries.
 */
final class DebugToolbarFactory
{
    public static function fromConfig(Config $config): DebugToolbarMiddleware
    {
        $debug = $config->get('http.error_handling.debug', false);
        if (!\is_bool($debug)) {
            throw new ConfigException('http.error_handling.debug must be a boolean.');
        }

        $debugAllowed = AppEnvironment::isDevelopment();

        return new DebugToolbarMiddleware($debug && $debugAllowed);
    }
}
