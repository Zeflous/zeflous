<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The family's negotiation table: the parsers in declaration order,
 * and the one that owns a request -- the roadmap's auto-negotiation
 * bullet, answered by walking the strategies until one claims the
 * request's media type (the bare type segment, parameters stripped,
 * extracted at {@see self::forRequest()}), and the first owner wins: the
 * shipped family's grammars are disjoint, so the order is declaration
 * hygiene rather than precedence, and a host-built table with
 * overlapping grammars answers its own first-declared parser -- the
 * seam is injectable exactly as the compression family's negotiator
 * is, so a test (or a host that knows its media types better than the
 * shipped table) can spell the family exactly.
 *
 * `multipart/form-data` owns no strategy here: the SAPI consumes
 * `php://input` for a multipart request before userland runs, so the
 * engine's own decode into the bags IS the multipart path, and a
 * userland parser could only ever serve a synthetic request. The DoS
 * posture actually in force for multipart is the one the ingress and
 * upload seams already answer -- the body budget at ingress
 * ({@see RequestBodyPolicy}, the eager claim and the limited stream),
 * the engine's own upload ceilings during the SAPI decode, and the
 * upload-tree normalisation ({@see ServerUploads}) -- and the
 * middleware's already-parsed gate preserves the SAPI's decoded body
 * untouched -- the table's own {@see self::forRequest()} gate (KDD-BP2,
 * `docs/ci-notes/http-body-parser.md`).
 */
final readonly class RequestBodyParsers
{
    /**
     * @param list<BodyParserInterface> $bodyParsers the family, in
     *                                               declaration order
     */
    public function __construct(
        private array $bodyParsers,
    ) {
    }

    /**
     * The shipped family over one policy: the four strategies, in
     * declaration order, with the JSON strategy reading the policy's
     * own depth ceiling.
     */
    public static function standard(BodyParserPolicy $bodyParserPolicy): self
    {
        return new self([
            new JsonBodyParser($bodyParserPolicy->jsonDepth),
            new XmlBodyParser(),
            new FormBodyParser(),
            new TextBodyParser(),
        ]);
    }

    /**
     * The parser that owns the media type, or null when the family
     * does not own it.
     *
     * @param string $mediaType the bare type segment, parameters
     *                          stripped
     */
    public function forMediaType(string $mediaType): ?BodyParserInterface
    {
        foreach ($this->bodyParsers as $bodyParser) {
            if ($bodyParser->supports($mediaType)) {
                return $bodyParser;
            }
        }

        return null;
    }

    /**
     * The parser that owns the request, or null when no parser does: a
     * request with no `Content-Type` line owns no parser, and a request
     * whose parsed body is already set owns none either -- another
     * seam's answer (the SAPI's own form decode at the request
     * factory's seam, or a host that parsed the body itself) is
     * answered as answered, never re-parsed (KDD-BP7).
     *
     * The type segment is extracted once, before the first `;`
     * parameter, trimmed -- the request factory's own ingress spelling
     * ({@see RequestFactory::formBody()}): a `Content-Type` line with
     * parameters (`application/json; charset=utf-8`) is the type the
     * grammar walks.
     */
    public function forRequest(ServerRequestInterface $serverRequest): ?BodyParserInterface
    {
        $mediaType = mb_trim(explode(';', $serverRequest->getHeaderLine('Content-Type'))[0]);

        if ($mediaType === '' || $serverRequest->getParsedBody() !== null) {
            return null;
        }

        return $this->forMediaType($mediaType);
    }

    /**
     * The deserialized body the family answers for this request, or
     * null when no decoration happens (the pass-through walks and the
     * JSON null document alike -- a null parsed body IS the PSR
     * contract's "no body" answer).
     *
     * The body is read through the non-consuming snapshot
     * ({@see StreamSnapshot::capture()}): the cursor is restored to
     * where it stood, so a controller that prefers the raw stream
     * still reads the whole body after the family parsed it, and a
     * stream that cannot be captured (neither tellable nor
     * restorable) passes the request through rather than draining it
     * -- the parser never destroys the request it was handed
     * (KDD-BP6). An empty body has nothing to parse: an empty body is
     * not a malformed document, and the SAPI does the same for an
     * empty form.
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
