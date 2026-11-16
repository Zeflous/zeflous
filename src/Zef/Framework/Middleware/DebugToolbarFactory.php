<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\AppEnvironment;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\StrictToggles;

/** Arms the toolbar only for explicit debug mode in a non-production environment.
 *
 * The debug knob reads through the strict toggle seam the error family
 * shares with every config-driven family ({@see StrictToggles}, #798):
 * the environment overlay's string spellings fold through the one
 * closed vocabulary, so `APP_HTTP__ERROR_HANDLING__DEBUG=on` arms the
 * same posture a native `true` does -- one knob, one grammar, whichever
 * factory reads it.
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
        $debug = StrictToggles::read(
            $config->get('http.error_handling.debug', false),
            'http.error_handling debug',
        );

        $debugAllowed = AppEnvironment::isDevelopment();

        return new DebugToolbarMiddleware($debug && $debugAllowed);
    }
}
