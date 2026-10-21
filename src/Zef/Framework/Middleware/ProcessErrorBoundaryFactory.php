<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Logging\JsonLineLogger;
use Zef\Framework\Telemetry\MeterInterface;

/**
 * Builds the process error boundary from the frozen configuration -- the
 * boundary family's own construction lane, mirroring {@see
 * ErrorMappingFactory}'s knob discipline one seam over: the same
 * `http.error_handling` knobs (the disclosure-gated debug posture, the
 * JSONL log path, the metrics enablement) answer the same here, because
 * both boundaries feed the same records to the same sinks. The boundary
 * itself owns the SAPI honesty fold the meter answers to ({@see
 * ProcessErrorBoundary}'s construction): a CLI process boots without
 * the counter, because a synthetic request's fabricated method would
 * lie in the `zef.http.errors.total` label set.
 *
 * The `process_boundary` knob's own grammar lives with the boot seam
 * that reads it ({@see self::wantsInstallation()}), so one spelling
 * validates once and answers everywhere. The SAPI spelling is a
 * construction seam the analysis battery drives ({@see
 * \Zef\Framework\Http\SapiTransmitter}'s sink doctrine): the default is
 * the running process's own, and the container's string-addressed
 * grammar never names it.
 *
 * @api the framework profile's `http.errors.process_boundary` service
 *      registration names this factory through the container's
 *      string-addressed grammar (`FrameworkDefaults::profile()`), so the
 *      analyser cannot see the call -- the same `@api` posture every
 *      sibling factory carries.
 */
final class ProcessErrorBoundaryFactory
{
    public static function fromConfig(
        Config $config,
        ?MeterInterface $meter = null,
        string $sapi = \PHP_SAPI,
    ): ProcessErrorBoundary {
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

        $environment = getenv('APP_ENV');
        $debugAllowed = \is_string($environment)
            && \in_array(mb_strtolower($environment), ['local', 'development', 'dev', 'test', 'testing'], true);

        return new ProcessErrorBoundary(
            $logPath === '' ? null : new JsonLineLogger($logPath),
            $debug && $debugAllowed,
            $metricsEnabled ? $meter : null,
            sapi: $sapi,
        );
    }

    /**
     * Whether the frozen configuration asks the boot seam to install the
     * process boundary -- the knob's single validation point.
     */
    public static function wantsInstallation(Config $config): bool
    {
        $processBoundary = $config->get('http.error_handling.process_boundary', false);
        if (!\is_bool($processBoundary)) {
            throw new ConfigException('http.error_handling.process_boundary must be a boolean.');
        }

        return $processBoundary;
    }
}
