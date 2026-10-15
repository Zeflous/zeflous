<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The CSRF family's double submit: the unsafe method's four-arms
 * verification, the discipline's own heart.
 *
 * The four arms are move-verbatim from the reference implementation
 * (legacy `zef-main` `Framework/Security/SecurityRuntimeMiddleware.php`,
 * the CSRF arm of milestone E2's runtime boundary): the cookie present,
 * the header present, the cookie signed by this secret, and the two
 * equal in constant time ({@see hash_equals()} -- never `===`, a
 * timing-observable comparison over a token is a partial-disclosure
 * oracle). An attacker who can satisfy all four arms with values this
 * secret signed is not a CSRF attacker; that is the whole discipline.
 */
final class CsrfDoubleSubmit
{
    /**
     * Answers whether the unsafe request honoured the double submit:
     * both channels present, the cookie signed, the pair equal in
     * constant time.
     */
    public static function honoured(
        CsrfTokenManager $csrfTokenManager,
        string $headerName,
        ServerRequestInterface $serverRequest,
        ?string $cookieToken,
    ): bool {
        $headerToken = $serverRequest->getHeaderLine($headerName);

        return $cookieToken !== null
            && $headerToken !== ''
            && $csrfTokenManager->isValid($cookieToken)
            && hash_equals($cookieToken, $headerToken);
    }
}
