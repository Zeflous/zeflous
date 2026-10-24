<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The body-parser family's PSR-15 boundary: the request decorated with
 * its deserialized body when the family owns the request's media type
 * -- the roadmap's Phase 3.1 multi-format parser, over the strategy
 * table ({@see RequestBodyParsers}) the config seam builds. The
 * boundary's own question is the family's arm and the decoration; the
 * walk itself -- which parser owns this request, and what that parser
 * answers for the body the request carries -- belongs to the table
 * ({@see RequestBodyParsers::parsedBody()}), the same two-questions
 * two-files fold the compression family taught.
 *
 * The order of the walk is cheapest-refusal-first, and every refusal
 * answers the handler the request itself, untouched: a disabled family
 * never reads the request's media type at all (the timing family's
 * inert posture); a request with no `Content-Type` line owns no parser;
 * a request whose parsed body is already set passes through -- the
 * SAPI's own form decode at the request factory's seam, or a host that
 * parsed the body itself, answered as answered, never re-parsed
 * (KDD-BP7); a media type the family does not own (multipart included)
 * passes through; and an empty or unreadable body has nothing to
 * parse. Only then does the owning strategy run, and a malformed
 * document refuses fail-closed with {@see MalformedBodyException}
 * BEFORE the handler -- the 400 the error boundary answers, instead of
 * a controller faulting one layer deeper on a null it was never told
 * about.
 *
 * The body is read through the non-consuming snapshot
 * ({@see StreamSnapshot::capture()}): the cursor is restored to where
 * it stood, so a controller that prefers the raw stream still reads
 * the whole body after the family parsed it, and a stream that cannot
 * be captured (neither tellable nor restorable) passes the request
 * through rather than draining it -- the parser never destroys the
 * request it was handed (KDD-BP6).
 */
final readonly class BodyParserMiddleware implements MiddlewareInterface
{
    private RequestBodyParsers $requestBodyParsers;

    /**
     * @param BodyParserPolicy        $bodyParserPolicy   the family's
     *                                                    frozen knob set
     * @param null|RequestBodyParsers $requestBodyParsers the strategy
     *                                                    table; defaults to
     *                                                    the shipped family
     *                                                    over the policy's
     *                                                    own depth ceiling
     */
    public function __construct(
        private BodyParserPolicy $bodyParserPolicy,
        ?RequestBodyParsers $requestBodyParsers = null,
    ) {
        $this->requestBodyParsers = $requestBodyParsers
            ?? RequestBodyParsers::standard($bodyParserPolicy);
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (!$this->bodyParserPolicy->enabled) {
            return $handler->handle($request);
        }

        $parsedBody = $this->requestBodyParsers->parsedBody($request);

        return $handler->handle(
            $parsedBody === null ? $request : $request->withParsedBody($parsedBody),
        );
    }
}
