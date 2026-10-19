<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Zef\Framework\Telemetry\MeterInterface;

/** Best-effort error metric emission isolated from the response path. */
final class ErrorFaultMetricsRecorder
{
    public static function record(
        ?MeterInterface $meter,
        Throwable $throwable,
        int $status,
        ServerRequestInterface $serverRequest,
    ): void {
        try {
            $meter?->increment('zef.http.errors.total', 1, [
                'http.request.method' => $serverRequest->getMethod(),
                'http.response.status_code' => $status,
                'exception.type' => self::exceptionType($throwable),
            ]);
        } catch (Throwable) {
            // Metrics are best-effort and must never replace the original failure.
        }
    }

    private static function exceptionType(Throwable $throwable): string
    {
        $class = $throwable::class;
        $separator = mb_strrpos($class, '\\');

        return $separator === false ? $class : mb_substr($class, $separator + 1);
    }
}
