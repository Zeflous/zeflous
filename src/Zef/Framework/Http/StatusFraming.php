<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The framing the status may carry -- the honest message the emitter
 * transmits, its framing header already reconciled against what the
 * status itself permits ({@see EmittedHeaders} keeps the emission).
 *
 * RFC 9110 section 8.6 forbids a Content-Length on any 1xx or 204
 * answer outright -- a keep-alive intermediary that trusts the field
 * for framing desynchronises -- so the forbidden classes lose the
 * header before the reconciliation runs: a clean `0` against an
 * empty body would otherwise reconcile and ship. A 304 may keep a
 * truthful one (section 8.6, second paragraph).
 */
final readonly class StatusFraming
{
    /**
     * The message with the framing header the status may carry.
     */
    public static function honest(ResponseInterface $response, int $status): MessageInterface
    {
        if ($status < 200 || $status === 204) {
            return $response->withoutHeader('Content-Length');
        }

        return ContentLength::reconcile($response, $response->getBody());
    }
}
