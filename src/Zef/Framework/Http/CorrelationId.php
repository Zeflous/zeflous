<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The request's correlation id: the `X-Request-ID` header's value when it
 * is a well-formed claim, a fresh 32-hex-digit token otherwise.
 *
 * The claim grammar is the reference implementation's (RFC 9110 token-ish,
 * at most 128 bytes) with one hardening deviation, engine-verified: the
 * legacy pattern's `$` anchor admitted a trailing newline ("abc\n" matched,
 * because `$` also matches immediately before a final newline), which would
 * have let a header-injected line break travel onto the response's own
 * `X-Request-ID` field line. The `D` modifier pins the anchor to the true
 * end of the value. The length bound lives inside the pattern itself --
 * `{1,128}` counts bytes in the pattern's byte mode -- so the empty claim
 * and the over-long claim are both grammar failures and the legacy's
 * separate `strlen` comparison folds away with them.
 *
 * The mint is once per request at the boundary (#796): a request that
 * already carries the error boundary's {@see ErrorRequestContext} answers
 * its immutable correlation identifier, whatever lane asks -- the
 * boundary minted the id the response will stamp before the inner pipeline
 * ran, so the slow-request record and the error lane's records join on the
 * identifier the response carries instead of each minting a fresh one a
 * claimless request could never correlate. The claim grammar itself
 * governs the mint only where the boundary has not yet run: the request's
 * own claim is honoured (or refused) at the boundary's single ask, and
 * bare constructions outside a boundary window keep the claim-or-mint
 * posture the process boundary and the facts memo ride.
 *
 * Attribution: legacy `zef-framework` `Middleware/GlobalErrorHandler`
 * (`process()`'s correlation id prologue, move-verbatim behaviour).
 */
final class CorrelationId
{
    private const string CLAIM_GRAMMAR = '/^[A-Za-z0-9._:-]{1,128}$/D';

    private const int GENERATED_BYTES = 16;

    /**
     * Answers the correlation id the request's response will carry: the
     * claimed `X-Request-ID` when the claim passes the grammar, or a fresh
     * 32-hex-digit token -- and the boundary's own mint verbatim when the
     * request already carries its context (#796), so every lane's record
     * and the response's stamp name one identifier.
     */
    public static function for(ServerRequestInterface $serverRequest): string
    {
        $requestContext = ErrorRequestContext::fromAttribute(
            $serverRequest->getAttribute(ErrorRequestContext::ATTRIBUTE),
        );

        if ($requestContext !== null) {
            return $requestContext->correlationId();
        }

        $claimed = $serverRequest->getHeaderLine('X-Request-ID');

        if (preg_match(self::CLAIM_GRAMMAR, $claimed) === 1) {
            return $claimed;
        }

        return bin2hex(random_bytes(self::GENERATED_BYTES));
    }
}
