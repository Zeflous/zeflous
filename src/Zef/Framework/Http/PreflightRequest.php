<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The CORS preflight detection (the fetch spec's definition): an OPTIONS
 * request that carries a non-empty `Access-Control-Request-Method`
 * header.
 *
 * Only the marker makes a preflight -- a plain OPTIONS call, even from an
 * allowed origin, is an ordinary request the handler must answer (audit
 * #328 in the reference lineage: the reference once short-circuited every
 * OPTIONS with a 204). The method comparison folds the request's method
 * token through the engine's full Unicode case map, so a foreign
 * implementation's lowercase `options` is still a preflight.
 */
final class PreflightRequest
{
    /**
     * Answers whether the request is a CORS preflight per the fetch
     * spec's definition.
     */
    public static function isPreflight(ServerRequestInterface $serverRequest): bool
    {
        return mb_strtoupper($serverRequest->getMethod()) === 'OPTIONS'
            && mb_trim($serverRequest->getHeaderLine('Access-Control-Request-Method')) !== '';
    }
}
