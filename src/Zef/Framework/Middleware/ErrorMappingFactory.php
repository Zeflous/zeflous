<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\AppEnvironment;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Logging\JsonLineLogger;
use Zef\Framework\Telemetry\MeterInterface;

/** Builds the global error boundary from the frozen configuration.
 *
 * The replay section's own read, validation, and wiring lives in its
 * {@see ErrorReplayWiring} fold -- the configuration family's per-key
 * validation weight is split by lane, not stacked on this one seam.
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

        $logger = $logPath === '' ? null : new JsonLineLogger($logPath);
        $replayStore = ErrorReplayWiring::store($config, $debug, $debugAllowed);

        return new ErrorMappingMiddleware(
            $logger,
            $debug && $debugAllowed,
            $metricsEnabled ? $meter : null,
            $replayStore,
        );
    }
}
