<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Logging\JsonLineLogger;
use Zef\Framework\Telemetry\MeterInterface;

/** Builds the global error boundary from the frozen configuration. */
final class ErrorMappingFactory
{
    public static function fromConfig(Config $config, ?MeterInterface $meter = null): ErrorMappingMiddleware
    {
        $debug = $config->get('http.error_handling.debug', false);
        if (!is_bool($debug)) {
            throw new ConfigException('http.error_handling.debug must be a boolean.');
        }

        $logPath = $config->get('http.error_handling.log_path', '');
        if (!is_string($logPath)) {
            throw new ConfigException('http.error_handling.log_path must be a string.');
        }

        $metricsEnabled = $config->get('observability.metrics.enabled', false);
        if (!is_bool($metricsEnabled)) {
            throw new ConfigException('observability.metrics.enabled must be a boolean.');
        }

        $environment = getenv('APP_ENV');
        $debugAllowed = is_string($environment)
            && in_array(strtolower($environment), ['local', 'development', 'dev', 'test', 'testing'], true);

        $replayEnabled = $config->get('http.error_handling.replay_enabled', false);
        if (!is_bool($replayEnabled)) {
            throw new ConfigException('http.error_handling.replay_enabled must be a boolean.');
        }

        $replayPath = $config->get('http.error_handling.replay_path', '');
        if (!is_string($replayPath)) {
            throw new ConfigException('http.error_handling.replay_path must be a string.');
        }

        if ($replayEnabled && (!$debug || !$debugAllowed || $replayPath === '')) {
            throw new ConfigException(
                'Request replay requires debug=true, a local APP_ENV, and a configured replay_path.',
            );
        }

        $logger = $logPath === '' ? null : new JsonLineLogger($logPath);
        $replayStore = $replayEnabled ? new ErrorReplayStore($replayPath) : null;

        return new ErrorMappingMiddleware(
            $logger,
            $debug && $debugAllowed,
            $metricsEnabled ? $meter : null,
            $replayStore,
        );
    }
}
