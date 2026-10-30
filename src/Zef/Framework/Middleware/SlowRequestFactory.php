<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Log\LoggerInterface;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\ObservabilityFamilies;
use Zef\Framework\Http\SlowRequestMiddleware;
use Zef\Framework\Http\SlowRequestPolicy;
use Zef\Framework\Logging\JsonLineLogger;

/**
 * The slow-request middleware's config-driven construction seam: the
 * factory the framework profile's own `observability.slow_request`
 * service registration names.
 *
 * The registration is declared as data in {@see
 * \Zef\Framework\FrameworkDefaults} -- `'factory' =>
 * SlowRequestFactory::class . '::fromConfig'` over the
 * `framework.config` service argument -- resolved through the
 * container's existing declarative grammar, exactly as the timing
 * family's own registration is. This class is the grammar's
 * string-addressed target, which is why it is marked `@api` rather
 * than being baselined or suppressed.
 *
 * Validation is the family's own ({@see SlowRequestPolicy::fromMap()}):
 * every knob read strictly, the unknown family and the unknown knob
 * each a named halt.
 *
 * The record's sink is the deployment's ONE structured log address: the
 * error-handling lane's own `http.error_handling.log_path`, read here the
 * same way the process boundary's factory reads it -- a configured path
 * answers the JSON line logger both lanes write through, so the
 * slow-request records and the error records land in the one sink and join
 * on the request identifier; an empty path derives to the storage layout's
 * `logs` lane when the host declared a root ({@see ErrorStorageWiring},
 * the one derivation fold the whole family shares), and only the silent
 * posture -- no explicit path and no declared root -- leaves the logger
 * null and the record's own writer carrying the SAPI fallback. The family
 * deliberately owns no log-path knob of its own: two knobs for one sink is
 * a fragmentation an operator must then reconcile, and the error lane
 * already named the address.
 *
 * @api
 */
final readonly class SlowRequestFactory
{
    /**
     * Builds the middleware from the frozen repository's
     * `observability.slow_request` subtree over the deployment's
     * structured log address.
     */
    public static function fromConfig(Config $config): SlowRequestMiddleware
    {
        $logPath = ErrorStorageWiring::logAddress($config);

        return new SlowRequestMiddleware(
            SlowRequestPolicy::fromMap(
                ObservabilityFamilies::subtree($config->get('observability'), 'slow_request'),
            ),
            self::logger($logPath),
        );
    }

    /**
     * The record's sink: the JSON line logger over the deployment's
     * one structured log address, or null when the configuration
     * answers none (no explicit path and no declared storage root).
     *
     * @return null|LoggerInterface the sink the slow-request records
     *                              travel through
     */
    private static function logger(string $logPath): ?LoggerInterface
    {
        if ($logPath === '') {
            return null;
        }

        return new JsonLineLogger($logPath);
    }
}
