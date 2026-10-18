<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Throwable;
use Zef\Framework\Exception\ZefException;

/** Maps HTTP error status and exception category to the PSR-3 severity. */
final class ErrorFaultSeverity
{
    public static function for(Throwable $exception, int $status): string
    {
        if ($status < 500) {
            return 'warning';
        }

        return $exception instanceof ZefException ? 'error' : 'critical';
    }
}
