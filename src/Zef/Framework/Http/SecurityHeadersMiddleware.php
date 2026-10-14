<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Security headers middleware -- the hardened response decoration, the
 * HTTP layer's fifth PSR-15 middleware.
 *
 * Every response the pipeline answers is stamped with the family's six
 * unconditional hardening headers ({@see BaselineSecurityHeaders}) plus
 * whichever toggled headers the policy arms ({@see
 * ToggledSecurityHeaders}): `Permissions-Policy` by default, and
 * `Content-Security-Policy` / `Strict-Transport-Security` when their knobs
 * are on (the latter only over `https`). The decoration replaces, never
 * appends: a handler that set `X-Frame-Options: SAMEORIGIN` is answered
 * with `DENY`, because the middleware is the hardening boundary -- the
 * reference's posture, preserved verbatim.
 *
 * OPT-IN: add the middleware to the stack of an application that wants
 * hardened responses; it is intentionally not part of any default stack.
 * The config-driven path is the framework profile's own `security.headers`
 * service registration ({@see \Zef\Framework\FrameworkDefaults}), whose
 * knobs (`security.headers.*`) are read, validated and frozen at boot --
 * see `docs/ci-notes/security-runtime.md` for the lane's decisions.
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Middleware/SecurityHeadersMiddleware.php`, milestone E2): the header
 * set, the values, the https-gated HSTS and the replace-not-append
 * semantics are move-verbatim; the construction is restructured -- the
 * reference read a raw policy array per call site, here the validated
 * knob set is {@see SecurityHeadersPolicy}, built once at boot through
 * the layered configuration.
 */
final readonly class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SecurityHeadersPolicy $securityHeadersPolicy,
    ) {
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return $this->decorated($handler->handle($request), $request->getUri()->getScheme());
    }

    /**
     * Stamps the hardened set onto one response: the baseline pairs
     * unconditionally, the toggled pairs the policy arms for this
     * request's scheme.
     */
    private function decorated(ResponseInterface $response, string $scheme): ResponseInterface
    {
        $pairs = [
            ...BaselineSecurityHeaders::pairs(),
            ...ToggledSecurityHeaders::pairs($this->securityHeadersPolicy, $scheme),
        ];

        $message = $response;
        foreach ($pairs as [$name, $value]) {
            $message = $message->withHeader($name, $value);
        }

        \assert($message instanceof ResponseInterface, 'the with* chain must preserve the response type.');

        return $message;
    }
}
