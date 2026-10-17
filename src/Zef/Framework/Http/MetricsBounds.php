<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The metrics family's fail-loud bounds: the endpoint path pair's
 * own grammar and collision refusal, the export family's {@see
 * ExportBounds} lineage landed as the bounds' own unit -- the CSRF
 * family's {@see CsrfBounds::assertUsableSecret()} grand lineage,
 * the halt an ARMED family with an unusable path answers.
 *
 * The grammar a usable endpoint path carries: absolute (the leading
 * slash), free of the control and space bytes a request target
 * grammar refuses, and free of the `?` and `#` separators that
 * belong to the query and fragment a path is not -- the middleware
 * judges the request's own path exactly, so a knob carrying the
 * separators would be a surface that never matches what the client
 * asks for.
 *
 * The collision refusal is the pair's own cross-knob invariant, the
 * retry lane's cap-never-below-delay lineage: the health surface
 * owns its base path and the two view paths deriving from it, and a
 * metrics path that collides with any of the three is a mis-declared
 * family -- one endpoint shadowed by another is a surface the
 * deployment believes is served while the collision never answers it.
 */
final class MetricsBounds
{
    /**
     * Asserts the endpoint path pair an armed family declared is
     * usable: each path's own grammar, then the collision refusal
     * over the health surface's three endpoints.
     *
     * @param string $metricsPath the exposition endpoint's path
     * @param string $healthPath  the health surface's base path
     *
     * @throws HttpException when a path fails its grammar, or the
     *                       pair collides
     */
    public static function assertUsablePaths(string $metricsPath, string $healthPath): void
    {
        self::assertUsablePath($metricsPath, 'observability.metrics metrics_path');
        self::assertUsablePath($healthPath, 'observability.metrics health_path');

        $healthSurface = [
            $healthPath,
            $healthPath . '/live',
            $healthPath . '/ready',
        ];
        if (\in_array($metricsPath, $healthSurface, true)) {
            throw new HttpException(
                'The observability.metrics metrics_path knob must not collide with the'
                . ' health surface it travels with (' . $metricsPath . ' is one of the'
                . ' health endpoints) -- rename one of the two paths.',
            );
        }
    }

    /**
     * Asserts one endpoint path's grammar: the leading slash, the
     * bytes a request target grammar refuses, the separators a path
     * is not.
     *
     * @throws HttpException when the path fails the grammar
     */
    private static function assertUsablePath(string $path, string $knob): void
    {
        if ($path === '') {
            throw new HttpException(
                "The {$knob} knob must not be empty -- the endpoint it names is the"
                . ' surface the family arms; declare the path explicitly or disarm the family.',
            );
        }

        if (!str_starts_with($path, '/')) {
            throw new HttpException(
                "The {$knob} knob must be an absolute path starting with '/', '{$path}' given.",
            );
        }

        if (preg_match('/[\x00-\x20\x7F]/', $path) === 1) {
            throw new HttpException(
                "The {$knob} knob must not carry control or space bytes, '{$path}' given.",
            );
        }

        if (str_contains($path, '?') || str_contains($path, '#')) {
            throw new HttpException(
                "The {$knob} knob must be a path, not a query or fragment carrier"
                . " ('{$path}' given) -- the query and the fragment belong to the client's"
                . ' request, never to the endpoint the family names.',
            );
        }
    }
}
