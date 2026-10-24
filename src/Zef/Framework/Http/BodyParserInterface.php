<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * One body parser's contract: the strategy face the multi-format
 * body-parser family ({@see BodyParserMiddleware}) negotiates over the
 * request's media type -- the roadmap HTTP Layer's Phase 3.1 strategy
 * pattern, one implementation per document family (JSON, XML, the
 * url-encoded form, plain text).
 *
 * {@see supports()} owns the media-type grammar: the bare type segment
 * the middleware extracted from the `Content-Type` line (parameters
 * already stripped), answered byte-wise case-insensitively -- only the
 * ASCII media-type spelling ever matches, so the fold never crosses a
 * multibyte boundary. A parser that does not own the type answers
 * false, and the request passes through the family untouched.
 *
 * {@see parse()} answers the deserialized body in the PSR-7 contract's
 * own shape -- an array, an object, or null ({@see ParsedBody::assert()}
 * guards the same triple at the request seam). The body handed over is
 * non-empty and already budget-bounded by the ingress policy; a
 * document that fails the parser's own grammar refuses fail-closed
 * with the layer's exception ({@see MalformedBodyException} for a
 * malformed document, {@see HttpException} for a runtime that cannot
 * parse what it owns), never a silent null -- the layer's doctrine,
 * replayed.
 *
 * The interface name follows the repository's `*Interface` convention,
 * not the roadmap's sketch spelling (`BodyParserStrategy`): the roadmap
 * prose also sketched `ZefStream` and `ZefRequest`, and the layer that
 * landed answers `Stream` and `Request` -- sketch names are sketches
 * (KDD-BP1, `docs/ci-notes/http-body-parser.md`).
 */
interface BodyParserInterface
{
    /**
     * Whether this parser owns the media type.
     *
     * @param string $mediaType the bare type segment of the request's
     *                          `Content-Type` line, parameters stripped
     */
    public function supports(string $mediaType): bool;

    /**
     * Deserializes one non-empty, budget-bounded body.
     *
     * @param string $body the request body as the middleware captured it
     *                     without consuming the stream
     *
     * @return null|array<array-key, mixed>|object the parsed body in the
     *                                             PSR contract's shape
     *
     * @throws HttpException when the document is malformed
     *                       ({@see MalformedBodyException}) or the runtime
     *                       cannot parse the media type this parser owns
     */
    public function parse(string $body): array|object|null;
}
