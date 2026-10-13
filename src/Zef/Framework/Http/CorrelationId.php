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
     * 32-hex-digit token.
     */
    public static function for(ServerRequestInterface $serverRequest): string
    {
        $claimed = $serverRequest->getHeaderLine('X-Request-ID');

        if (preg_match(self::CLAIM_GRAMMAR, $claimed) === 1) {
            return $claimed;
        }

        return bin2hex(random_bytes(self::GENERATED_BYTES));
    }
}
