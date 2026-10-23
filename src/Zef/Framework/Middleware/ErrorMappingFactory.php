<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\AppEnvironment;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Http\ErrorReplayStore;
use Zef\Framework\Logging\JsonLineLogger;
use Zef\Framework\Telemetry\MeterInterface;

/** Builds the global error boundary from the frozen configuration.
 *
 * @api the framework profile's `http.errors` service registration names
 *      this factory through the container's string-addressed grammar
 *      (`FrameworkDefaults::profile()`), so the analyser cannot see the
 *      call -- the same `@api` posture every sibling factory carries.
 */
final class ErrorMappingFactory
{
    public static function fromConfig(Config $config, ?MeterInterface $meter = null): ErrorMappingMiddleware
    {
        $debug = $config->get('http.error_handling.debug', false);
        if (!\is_bool($debug)) {
            throw new ConfigException('http.error_handling.debug must be a boolean.');
        }

        $logPath = $config->get('http.error_handling.log_path', '');
        if (!\is_string($logPath)) {
            throw new ConfigException('http.error_handling.log_path must be a string.');
        }

        $metricsEnabled = $config->get('observability.metrics.enabled', false);
        if (!\is_bool($metricsEnabled)) {
            throw new ConfigException('observability.metrics.enabled must be a boolean.');
        }

        $debugAllowed = AppEnvironment::isDevelopment();

        $replayEnabled = $config->get('http.error_handling.replay_enabled', false);
        if (!\is_bool($replayEnabled)) {
            throw new ConfigException('http.error_handling.replay_enabled must be a boolean.');
        }

        $replayPath = $config->get('http.error_handling.replay_path', '');
        if (!\is_string($replayPath)) {
            throw new ConfigException('http.error_handling.replay_path must be a string.');
        }

        $replayMaxSnapshots = $config->get('http.error_handling.replay_max_snapshots', 1000);
        if (!\is_int($replayMaxSnapshots) || $replayMaxSnapshots < 1) {
            throw new ConfigException('http.error_handling.replay_max_snapshots must be an integer of 1 or above.');
        }

        if ($replayEnabled && (!$debug || !$debugAllowed || $replayPath === '')) {
            throw new ConfigException(
                'Request replay requires debug=true, a local APP_ENV, and a configured replay_path.',
            );
        }

        $logger = $logPath === '' ? null : new JsonLineLogger($logPath);
        $replayStore = $replayEnabled ? new ErrorReplayStore($replayPath, $replayMaxSnapshots) : null;

        return new ErrorMappingMiddleware(
            $logger,
            $debug && $debugAllowed,
            $metricsEnabled ? $meter : null,
            $replayStore,
        );
    }
}
