<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/** Arms the toolbar only for explicit debug mode in a non-production environment. */
final class DebugToolbarFactory
{
    public static function fromConfig(Config $config): DebugToolbarMiddleware
    {
        $debug = $config->get('http.error_handling.debug', false);
        if (!is_bool($debug)) {
            throw new ConfigException('http.error_handling.debug must be a boolean.');
        }

        $environment = getenv('APP_ENV');
        $debugAllowed = is_string($environment)
            && in_array(strtolower($environment), ['local', 'development', 'dev', 'test', 'testing'], true);

        return new DebugToolbarMiddleware($debug && $debugAllowed);
    }
}
