<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\AppEnvironment;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\StrictToggles;
use Zef\Framework\Logging\JsonLineLogger;
use Zef\Framework\Telemetry\MeterInterface;

/** Builds the global error boundary from the frozen configuration.
 *
 * The boolean knobs read through the strict toggle seam every
 * config-driven family shares ({@see StrictToggles}): a native bool
 * passes through, the environment overlay's string spellings
 * (`APP_HTTP__ERROR_HANDLING__DEBUG=true` -- {@see \Zef\Framework\Config\Merge\EnvSource}
 * passing values through as the environment carries them) fold through
 * the one closed vocabulary, and a nonsense spelling halts the boot with
 * the knob named (#798) -- the same grammar the observability families'
 * own policies already read `observability.metrics enabled` through, so
 * one knob spells one answer whichever seam reads it.
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
        $debug = StrictToggles::read(
            $config->get('http.error_handling.debug', false),
            'http.error_handling debug',
        );

        $logAddress = ErrorStorageWiring::logAddress($config);

        $metricsEnabled = StrictToggles::read(
            $config->get('observability.metrics.enabled', false),
            'observability.metrics enabled',
        );

        $debugAllowed = AppEnvironment::isDevelopment();

        $logger = $logAddress === '' ? null : new JsonLineLogger($logAddress);
        $replayStore = ErrorReplayWiring::store($config, $debug, $debugAllowed);

        return new ErrorMappingMiddleware(
            $logger,
            $debug && $debugAllowed,
            $metricsEnabled ? $meter : null,
            $replayStore,
        );
    }
}
