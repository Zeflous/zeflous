<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Zef\Framework\Http\HttpWireBody;
use Zef\Framework\Http\HttpWireRequest;
use Zef\Framework\Http\HttpWireResponse;

/**
 * The raw dump command's text form: the answered exchange as the wire
 * carries it -- the request block and the response block over their
 * own section markers, each rendered by the HTTP layer's own wire
 * folds at the body bound the family defaults to.
 */
final readonly class HttpDumpText
{
    /**
     * The whole answered exchange as one wire dump.
     */
    public static function rendered(HttpExchange $httpExchange): string
    {
        $bodyBound = HttpWireBody::DEFAULT_BOUND;

        return "-- request --\r\n"
            . HttpWireRequest::rendered($httpExchange->request, $bodyBound)
            . "\r\n\r\n-- response --\r\n"
            . HttpWireResponse::rendered($httpExchange->response, $bodyBound);
    }
}
