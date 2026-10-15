<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CSRF middleware -- the double-submit boundary, the HTTP layer's
 * seventh PSR-15 middleware.
 *
 * An armed policy ({@see CsrfPolicy::$enabled}) enforces the double-
 * submit discipline over the two submit channels:
 *
 * - safe methods (`GET`, `HEAD`, `OPTIONS`, `TRACE`): the request needs
 *   no token; a request that arrives without the family's cookie is
 *   answered with a fresh one (`Set-Cookie`, replace-not-append -- the
 *   token boundary wins over handler-set values), and a request that
 *   already carries one passes untouched (no re-issue: churning a
 *   browser's token on every navigation races the page's own POSTs);
 * - unsafe methods (everything else): the request must carry the
 *   family's cookie AND a header token, the cookie must be one the
 *   secret signed ({@see CsrfTokenManager::isValid()}), and the header
 *   must equal the cookie byte-for-byte in constant time
 *   ({@see CsrfDoubleSubmit}) -- the double submit. Anything else answers
 *   `403 Forbidden` in the layer's JSON error envelope ({@see
 *   ErrorResponseFactory}), so the client correlates the refusal exactly
 *   like any other mapped failure.
 *
 * The cookie attributes are policy-controlled throughout through the
 * family's own issuance line ({@see CsrfSetCookie}: `Path=/`,
 * `SameSite` from the vocabulary, `Secure` only over https when the
 * knob asks, `HttpOnly` when the knob asks) and the value is always
 * the freshly issued token -- no request input is embedded. The
 * cookie the request carries is read through the family's own pair
 * grammar ({@see CsrfCookieValue}). An attacker who can satisfy both
 * channels with values this secret signed is not a CSRF attacker;
 * that is the whole discipline.
 *
 * A disarmed policy is a pass-through: the middleware in the stack is
 * the boundary, the knob arms it -- the emergency switch an env overlay
 * can flip without touching the stack. The factory builds the token
 * manager only for an armed policy (the disarmed one carries no usable
 * secret by construction, and a boundary that will never consult a
 * manager carries none), which is why the collaborator is nullable and
 * the armed path asserts the invariant the factory guarantees.
 *
 * OPT-IN: add the middleware to the stack of an application that wants
 * the double-submit boundary; it is intentionally not part of any
 * default stack. The config-driven path is the framework profile's own
 * `security.csrf` service registration ({@see
 * \Zef\Framework\FrameworkDefaults}), whose knobs are validated and
 * frozen at boot -- and an armed registration with no secret halts the
 * boot rather than running unprotectedly ({@see CsrfBounds}).
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Security/SecurityRuntimeMiddleware.php`, the CSRF arm of
 * milestone E2's runtime boundary): the safe-method set, the four-arms
 * refusal condition, the cookie parsing and the attribute order live
 * move-verbatim in the family's own micro-classes ({@see CsrfCookieValue},
 * {@see CsrfSetCookie}); restructured -- the reference resolved the token
 * discipline inside one runtime middleware shared with rate limiting
 * and origin policy; here the boundary is its own middleware composed
 * like any other, and the refusal body uses the layer's single error
 * envelope (the reference hand-rolled a second JSON shape) with the
 * correlation id always present.
 */
final readonly class CsrfMiddleware implements MiddlewareInterface
{
    /** The invariant every with*() chain upholds: the PSR-7 instance type. */
    private const string PRESERVED_RESPONSE_TYPE = 'withHeader() must preserve the response type.';

    /** The methods a request needs no token for, move-verbatim. */
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

    public function __construct(
        private CsrfPolicy $csrfPolicy,
        private ?CsrfTokenManager $csrfTokenManager,
    ) {
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (!$this->csrfPolicy->enabled) {
            return $handler->handle($request);
        }

        return $this->enforce($request, $handler);
    }

    /**
     * The armed path: read the family's cookie, answer the safe-method
     * issuance or the unsafe-method double submit, and decorate the
     * handler's response with the fresh token when one was issued.
     */
    private function enforce(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        \assert($this->csrfTokenManager instanceof CsrfTokenManager, 'an armed policy carries its token manager.');

        $cookieToken = CsrfCookieValue::from($request->getHeaderLine('Cookie'), $this->csrfPolicy->cookieName);
        $headerName = $this->csrfPolicy->headerName;
        $issued = null;

        if (\in_array(mb_strtoupper($request->getMethod()), self::SAFE_METHODS, true)) {
            if ($cookieToken === null) {
                $issued = $this->csrfTokenManager->issue();
            }
        } elseif (!CsrfDoubleSubmit::honoured($this->csrfTokenManager, $headerName, $request, $cookieToken)) {
            return $this->refused($request);
        }

        $response = $handler->handle($request);

        return $issued === null ? $response : $this->withFreshCookie($response, $issued, $request);
    }

    /**
     * The refusal answer: 403 in the layer's error envelope, the
     * correlation id always present.
     */
    private function refused(ServerRequestInterface $request): ResponseInterface
    {
        return ErrorResponseFactory::create(403, 'Forbidden', CorrelationId::for($request));
    }

    /**
     * The issuance decoration: the fresh token as the family's cookie
     * ({@see CsrfSetCookie}), replace-not-append.
     */
    private function withFreshCookie(
        ResponseInterface $response,
        string $token,
        ServerRequestInterface $request,
    ): ResponseInterface {
        $message = $response->withHeader(
            'Set-Cookie',
            CsrfSetCookie::headerValue($this->csrfPolicy, $token, $request->getUri()->getScheme()),
        );

        \assert($message instanceof ResponseInterface, self::PRESERVED_RESPONSE_TYPE);

        return $message;
    }
}
