<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Container\Container;
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
    /**
     * The container service identifier the boundary registers under at
     * boot ({@see \Zef\Framework\FrameworkDefaults::PROCESS_BOUNDARY_SERVICE}
     * aliases this spelling) -- the `http.errors` precedent's own family,
     * so the error lane's services stay one catalogue. The identifier
     * lives here, with the family that owns it, so the registration seam
     * never circles a dependency back to the profile that registers it.
     */
    public const string SERVICE_ID = 'http.errors.process_boundary';

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
            transmission: new ProcessBoundaryTransmission(sapi: $sapi),
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

    /**
     * The boot seam's own resolver: answers the boundary the frozen
     * configuration asks the one-call boot to install, through the
     * profile's own service registration (a host's same-identifier
     * rebind wins over the framework's default construction) -- or null
     * when the knob stays silent, so the seam's caller spells one
     * conditional and nothing else.
     *
     * A knob asking for the boundary cannot be answered by something
     * else: a service that resolves to a non-boundary halts the boot with
     * the identifier, the expected class and the given type named.
     */
    public static function resolveForInstallation(Config $config, Container $container): ?ProcessErrorBoundary
    {
        if (!self::wantsInstallation($config)) {
            return null;
        }

        $boundary = $container->get(self::SERVICE_ID);

        if (!$boundary instanceof ProcessErrorBoundary) {
            throw new ConfigException(\sprintf(
                'The "%s" service must resolve to a %s instance, "%s" given.',
                self::SERVICE_ID,
                ProcessErrorBoundary::class,
                get_debug_type($boundary),
            ));
        }

        return $boundary;
    }
}
