<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The family's negotiation table: the parsers in declaration order,
 * and the one that owns a media type -- the roadmap's auto-negotiation
 * bullet, answered by walking the strategies until one claims the
 * type, the first owner winning: the
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
 * untouched -- the reader's own request-level gate
 * ({@see ParsedBodyReader::forRequest()}; KDD-BP2,
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
        return new self(StandardBodyParsers::family($bodyParserPolicy));
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
}
