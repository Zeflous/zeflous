<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Throwable;
use Zef\Framework\Exception\CarriesHttpStatus;

/**
 * The error status a failure maps to: the typed tier first, the layer's
 * coarse shim second, the internal default last.
 *
 * Three tiers resolve the status (ROADMAP.md, "Error Handling System",
 * phase 1, retiring the message sniffing):
 *
 * 1. A {@see CarriesHttpStatus} exception answers the status its
 *    contract encodes -- the typed family ({@see \Zef\Framework\Exception\NotFoundException}
 *    404, {@see \Zef\Framework\Exception\MethodNotAllowedException} 405,
 *    {@see PayloadTooLargeException} 413, ...) and any host-defined
 *    implementer join the same resolution without a map edit.
 * 2. An untyped {@see HttpException} is the layer's own fail-closed
 *    rejection surfacing through the stack -- a malformed request at
 *    the boundary until the router lane's method signal and the typed
 *    family's finer members took their own classes, so it answers the
 *    coarse 400.
 * 3. Anything else is an application fault: the 500 that the error
 *    middleware logs before answering.
 *
 * The message is never consulted: a signal that must reach a status
 * code is a class, not a string comparison -- the map that matched the
 * body-budget refusal by its literal text could be defeated by any
 * layer refusalling with the same wording, and could not see a
 * differently-worded one at all.
 */
final class ErrorStatusMap
{
    private const int BAD_REQUEST = 400;

    private const int INTERNAL_SERVER_ERROR = 500;

    /**
     * Answers the client-facing status for a failure.
     */
    public static function statusFor(Throwable $throwable): int
    {
        if ($throwable instanceof CarriesHttpStatus) {
            return $throwable->httpStatus();
        }

        return $throwable instanceof HttpException ? self::BAD_REQUEST : self::INTERNAL_SERVER_ERROR;
    }
}
