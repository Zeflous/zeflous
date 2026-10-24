<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The family's request reader: the walk that answers what the
 * body-parser family makes of one request -- which parser the
 * negotiation table owns for it, and what that parser answers for the
 * request's own body ({@see ReplayBodyReader}'s naming mirrored: this
 * one reads the parsed body, not the replay snapshot).
 *
 * The walk is cheapest-refusal-first, and every refusal answers null
 * -- the PSR contract's own "no body" -- never a parse that was not
 * asked for: a request with no `Content-Type` line owns no parser; a
 * request whose parsed body is already set owns none either -- another
 * seam's answer (the SAPI's own form decode at the request factory's
 * seam, or a host that parsed the body itself) is answered as
 * answered, never re-parsed (KDD-BP7); and an empty or uncapturable
 * body has nothing to parse. Only then does the owning strategy run,
 * and a malformed document refuses fail-closed with
 * {@see MalformedBodyException} before any handler sees the request.
 *
 * The body is read through the non-consuming snapshot
 * ({@see StreamSnapshot::capture()}): the cursor is restored to where
 * it stood, so a controller that prefers the raw stream still reads
 * the whole body after the family parsed it, and a stream that cannot
 * be captured (neither tellable nor restorable) passes the request
 * through rather than draining it -- the parser never destroys the
 * request it was handed (KDD-BP6).
 */
final readonly class ParsedBodyReader
{
    /**
     * @param RequestBodyParsers $requestBodyParsers the negotiation table the
     *                                               walk consults
     */
    public function __construct(
        private RequestBodyParsers $requestBodyParsers,
    ) {
    }

    /**
     * The parser the table owns for this request, or null when no
     * parser does.
     *
     * The type segment is the request's own media type
     * ({@see RequestMediaType::segment()}, the request factory's own
     * ingress spelling lifted to the seam).
     */
    public function forRequest(ServerRequestInterface $serverRequest): ?BodyParserInterface
    {
        $mediaType = RequestMediaType::segment($serverRequest);

        if ($mediaType === '' || $serverRequest->getParsedBody() !== null) {
            return null;
        }

        return $this->requestBodyParsers->forMediaType($mediaType);
    }

    /**
     * The deserialized body the family answers for this request, or
     * null when no decoration happens (the pass-through walks and the
     * JSON null document alike -- a null parsed body IS the PSR
     * contract's "no body" answer).
     *
     * @return null|array<array-key, mixed>|object the parsed body, or null
     *
     * @throws MalformedBodyException when the owning strategy refuses
     *                                the document
     */
    public function parsedBody(ServerRequestInterface $serverRequest): array|object|null
    {
        $bodyParser = $this->forRequest($serverRequest);

        if (!$bodyParser instanceof BodyParserInterface) {
            return null;
        }

        $body = StreamSnapshot::capture($serverRequest->getBody());

        if ($body === null || $body === '') {
            return null;
        }

        return $bodyParser->parse($body);
    }
}
