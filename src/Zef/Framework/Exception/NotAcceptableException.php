<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use Override;

/**
 * Raised when content negotiation rejects every representation a matched
 * route produces (ROADMAP.md, "Error Handling System", phase 1: the
 * exception hierarchy) -- the representation refusal (406).
 *
 * Carries the refusal's evidence: every media type the matched route does
 * produce, the payload the error response's detail is built from, so the
 * renderer never re-derives what the negotiation already resolved
 * ({@see \Zef\Framework\Config\Routing\RouteAcceptNegotiator} computes it
 * from the route's `produces` declarations; the legacy's dispatcher
 * hand-built the same list into an inline problem response).
 *
 * The router lane's dispatch translation
 * ({@see \Zef\Framework\Middleware\RouteDispatchHandler}) is the throw
 * site: a matched route whose `produces` vocabulary answers none of the
 * request's Accept header raises this refusal, and the request-scoped
 * error boundary renders it with the same RFC 9457 posture every other
 * typed refusal answers -- so the client sees one 406 shape whether the
 * miss happened in the pattern, the method or the negotiation.
 *
 * The media-types list is validated here, the value-object discipline of
 * the declaration grammars: a representation refusal that names no
 * producible media type is a defect (a route with `produces` answered the
 * match by construction, so at least one type exists), and an entry that
 * is not a non-empty string is a malformed signal -- both are refused
 * rather than silently repaired.
 *
 * @api the typed family's published refusal vocabulary: hosts catch this
 *      across their own code exactly like the 404/405 members beside it.
 */
final class NotAcceptableException extends ZefException implements CarriesHttpStatus
{
    /**
     * Every media type the matched route produces, in the order given.
     *
     * @var list<non-empty-string>
     */
    public readonly array $acceptableMediaTypes;

    /**
     * @param array<array-key, mixed> $acceptableMediaTypes every media type the matched route produces
     */
    public function __construct(array $acceptableMediaTypes)
    {
        $this->acceptableMediaTypes = $this->validated($acceptableMediaTypes);

        parent::__construct('Supported media types: ' . implode(', ', $this->acceptableMediaTypes));
    }

    #[Override]
    public function httpStatus(): int
    {
        return 406;
    }

    /**
     * Judges the media-types list every representation refusal must carry:
     * at least one type, every entry a non-empty string.
     *
     * @param array<array-key, mixed> $acceptableMediaTypes
     *
     * @return list<non-empty-string>
     */
    private function validated(array $acceptableMediaTypes): array
    {
        if ($acceptableMediaTypes === []) {
            throw new InvariantViolationException(
                'A not-acceptable refusal must name at least one acceptable media type.',
            );
        }

        $validated = [];
        foreach ($acceptableMediaTypes as $acceptableMediumType) {
            if (!\is_string($acceptableMediumType) || $acceptableMediumType === '') {
                throw new InvariantViolationException(
                    'An acceptable media type must be a non-empty string, '
                    . get_debug_type($acceptableMediumType) . ' given.',
                );
            }

            $validated[] = $acceptableMediumType;
        }

        return $validated;
    }
}
