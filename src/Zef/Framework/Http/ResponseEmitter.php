<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The SAPI-aware response emitter: the runtime adapter's outbound half.
 *
 * Sends a PSR-7 response to the client -- the status code and validated
 * header fields through the {@see SapiTransmitter} seam, the body to the
 * output channel -- with the reference implementation's ordering and
 * safeguards, move-verbatim: the body stream is rewound first (middleware
 * that inspects the body, like an ETag hasher, leaves a seekable stream's
 * cursor at the end), headers are only transmitted while the SAPI has not
 * already shipped its own, and a lying Content-Length is reconciled away
 * before a single octet leaves the process.
 *
 * @api
 */
final readonly class ResponseEmitter
{
    private SapiTransmitter $sapiTransmitter;

    /**
     * @param null|SapiTransmitter $sapiTransmitter the transmission seam; the
     *                                              default transmits to the real SAPI, a recording transmitter
     *                                              observes what would be shipped
     */
    public function __construct(?SapiTransmitter $sapiTransmitter = null)
    {
        $this->sapiTransmitter = $sapiTransmitter ?? new SapiTransmitter();
    }

    /**
     * Emits the response.
     *
     * @param bool $closeBody    close the body stream after emission, on
     *                           success and failure alike
     * @param bool $suppressBody ship headers and status only; the body is
     *                           neither read nor echoed
     */
    public function emit(ResponseInterface $response, bool $closeBody = false, bool $suppressBody = false): void
    {
        $stream = $response->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        if (!$this->sapiTransmitter->sent()) {
            EmittedHeaders::transmit($response, $this->sapiTransmitter);
        }

        EmittedBody::emit($response, $suppressBody, $closeBody);
    }
}
