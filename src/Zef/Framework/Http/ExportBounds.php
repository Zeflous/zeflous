<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The export family's bounds and fail-loud seams: the constructor's
 * four numeric bounds with their one cross-knob invariant, and the
 * armed family's endpoint and service-identity grammar -- extracted
 * from {@see ExportPolicy} when the archaeology gate demanded the
 * split, following the CSRF family's own {@see CsrfBounds} lineage
 * the policy's docblock already named: a policy is frozen data, and
 * the halts it declares are a unit of their own.
 *
 * Every refusal spells its own remediation -- the knob named, the
 * value given, the environment overlay or configuration file or
 * explicit disarm that answers it -- because a mis-declared
 * deployment posture must halt loud, never fall back to a default
 * the deployment did not choose.
 */
final class ExportBounds
{
    /**
     * Asserts the constructor's four numeric bounds and the one
     * cross-knob invariant: the timeout's own range, the retry
     * count's, the two delays', and the cap never sitting below the
     * initial delay -- a backoff that starts above its own ceiling
     * is a mis-declared policy, not a policy.
     */
    public static function assertConstructorBounds(
        int $timeoutMs,
        int $maxRetries,
        int $retryDelayMs,
        int $retryDelayCapMs,
    ): void {
        if ($timeoutMs < 1 || $timeoutMs > 10000) {
            throw new HttpException('The observability.export timeout_ms knob must be between 1 and 10000.');
        }

        if ($maxRetries < 0 || $maxRetries > 10) {
            throw new HttpException('The observability.export max_retries knob must be between 0 and 10.');
        }

        if ($retryDelayMs < 0 || $retryDelayMs > 10000) {
            throw new HttpException('The observability.export retry_delay_ms knob must be between 0 and 10000.');
        }

        if ($retryDelayCapMs < 0 || $retryDelayCapMs > 60000) {
            throw new HttpException('The observability.export retry_delay_cap_ms knob must be between 0 and 60000.');
        }

        if ($retryDelayCapMs < $retryDelayMs) {
            throw new HttpException(
                'The observability.export retry_delay_cap_ms knob must not sit below retry_delay_ms.',
            );
        }
    }

    /**
     * The armed family's endpoint seam: an absolute HTTP or HTTPS
     * URL without embedded credentials, the reference's own
     * validation grammar -- a collector address that is not one of
     * those is a knob the resolution halts on, with the remediation
     * spelled out.
     *
     * @throws HttpException when the endpoint is empty, relative,
     *                       wrongly-schemed, or credential-bearing
     */
    public static function assertUsableEndpoint(string $endpoint): void
    {
        if (mb_trim($endpoint) === '') {
            throw new HttpException(
                'The observability.export family is armed but its endpoint knob is empty -- set '
                . "observability.export endpoint to the collector's absolute HTTP/HTTPS URL "
                . '(the environment overlay APP_OBSERVABILITY__EXPORT__ENDPOINT spells it), '
                . 'or disarm the family.',
            );
        }

        $parts = parse_url($endpoint);

        if (
            !\is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !\in_array(mb_strtolower($parts['scheme']), ['http', 'https'], true)
        ) {
            throw new HttpException(
                'The observability.export endpoint knob must be an absolute HTTP/HTTPS URL, '
                . "'" . $endpoint . "' given.",
            );
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new HttpException(
                'The observability.export endpoint knob must not carry embedded credentials -- '
                . "the transport's own failure diagnostics would echo them into exception text.",
            );
        }
    }

    /**
     * The armed family's service identity seam: a non-empty name --
     * the resource block's `service.name` is the one attribute every
     * collector groups by, and an empty one is a knob the resolution
     * halts on, with the remediation spelled out.
     *
     * @throws HttpException when the service name is empty
     */
    public static function assertUsableServiceName(string $serviceName): void
    {
        if (mb_trim($serviceName) === '') {
            throw new HttpException(
                'The observability.export service_name knob must be a non-empty string when the '
                . 'family is armed -- set it through the environment overlay '
                . 'APP_OBSERVABILITY__EXPORT__SERVICE_NAME or a configuration file, or disarm '
                . 'the family.',
            );
        }
    }
}
