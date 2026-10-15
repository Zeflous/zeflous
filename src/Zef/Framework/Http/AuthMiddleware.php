<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Closure;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Auth middleware -- the bearer boundary, the HTTP layer's eighth
 * PSR-15 middleware.
 *
 * An armed policy ({@see AuthPolicy::$enabled}) admits every request
 * it sees through the distributed security boundary ({@see
 * AuthBoundary}): the bearer credential the `Authorization` header
 * carries is resolved ({@see CredentialProviderInterface}), the
 * authenticated principal is authorized ({@see
 * AuthorizationPolicyInterface}), and the `X-Replay-Id` header a
 * state-mutating client echoes is held single-use inside its window
 * ({@see BoundedReplayProtector}). A request that clears all three
 * reaches the handler with its principal stamped as the
 * `zef.security.principal` attribute; anything else is refused in
 * the layer's JSON error envelope ({@see ErrorResponseFactory}) with
 * the correlation id always present -- the client correlates an
 * authentication refusal exactly like any other mapped failure.
 *
 * The refusal surface is the port's standards answer where the
 * reference hand-rolled one ({@see AuthRefusal}): every 401 carries
 * the `WWW-Authenticate` challenge RFC 9110 demands and RFC 6750
 * spells, the outage failures answer the fail-closed 503 with a
 * retry window, and the body stays with the layer's disclosure
 * discipline -- the public reason phrase, never the internal failure
 * taxonomy the reference echoed to anonymous callers.
 *
 * The input-shape seam sits before the boundary, fail-closed both
 * ways the reference crashed: an `X-Replay-Id` longer than the
 * request value object's own bound is refused as the replay
 * rejection it is (the reference's request constructor threw a 500
 * on the attacker's bytes), and the request labels the boundary
 * reads are truncated to their bounds (`method path`, the resource,
 * the action -- the reference's untruncated operation label threw
 * the same 500 on any path longer than the label's bound).
 *
 * OPT-IN: add the middleware to the stack of an application that
 * wants the bearer boundary; it is intentionally not part of any
 * default stack. The config-driven path is the framework profile's
 * own `security.auth` service registration ({@see
 * \Zef\Framework\FrameworkDefaults}), whose knobs are validated and
 * frozen at boot -- and an armed registration with no static tokens
 * halts the boot rather than running unprotectedly ({@see
 * AuthBounds}).
 *
 * Ported from the reference implementation (legacy `zef-main`
 * `Framework/Security/AuthenticationMiddleware.php` over the
 * `Framework/Security/Distributed/` family, milestone E2's
 * distributed boundary): the safe-method set, the anonymous-handle
 * resolution, the operation/resource/action labels and the
 * per-request admission order are move-verbatim in the family's own
 * classes; restructured -- the boundary composes the provider, the
 * policy and the protector once (the reference passed the last two
 * through every `admit()` call), the clock is one injectable reading
 * per request (the reference read `microtime()` twice, readings a
 * single request could disagree over), and the extraction grammar is
 * the RFC's own rather than the reference's permissive `\s+(.+)`
 * (a tab is not a separator and a comma-joined challenge line is not
 * a credential -- both are anonymous now, where the reference
 * forwarded the bytes to the provider).
 */
final readonly class AuthMiddleware implements MiddlewareInterface
{
    /** The methods that need no credential to reach the boundary. */
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

    /** The attribute the allowed request's principal travels under. */
    private const string PRINCIPAL_ATTRIBUTE = 'zef.security.principal';

    /** The clock seam, one reading per request.
     *
     * @var Closure():int
     */
    private Closure $now;

    /**
     * @param null|AuthBoundary    $authBoundary the composed boundary --
     *                                           null only for the
     *                                           disarmed policy the
     *                                           factory builds (a
     *                                           boundary that will never
     *                                           be consulted carries
     *                                           no collaborators)
     * @param null|(Closure():int) $now          the clock seam, Unix
     *                                           milliseconds; defaults
     *                                           to the system clock
     *                                           ({@see AuthClock})
     */
    public function __construct(
        private AuthPolicy $authPolicy,
        private ?AuthBoundary $authBoundary = null,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? AuthClock::milliseconds(...);
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (!$this->authPolicy->enabled) {
            return $handler->handle($request);
        }

        return $this->admission($request, $handler);
    }

    /**
     * The armed path: extract the credential, refuse the malformed
     * replay channel, admit through the boundary, and stamp the
     * principal the allowed request carries.
     */
    private function admission(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        \assert($this->authBoundary instanceof AuthBoundary, 'an armed policy carries its boundary.');

        $method = mb_strtoupper($request->getMethod());
        $credential = AuthCredential::fromBearerLine($request->getHeaderLine('Authorization'));
        $replayId = $request->getHeaderLine('X-Replay-Id');

        $inputRefusal = $this->refuseInput($method, $credential, $replayId);

        if ($inputRefusal instanceof AuthRefusal) {
            return $inputRefusal->response($request);
        }

        $authAdmission = $this->authBoundary->admit(
            $credential,
            AuthRequest::fromRequest($method, $request->getUri()->getPath(), $replayId),
            ($this->now)(),
        );

        if (!$authAdmission->allows()) {
            return AuthRefusal::for($authAdmission->failure)->response($request);
        }

        \assert($authAdmission->context instanceof AuthContext, 'an allowed admission carries its context.');

        return $handler->handle(
            $request->withAttribute(self::PRINCIPAL_ATTRIBUTE, $authAdmission->context->principalId),
        );
    }

    /**
     * The input-shape seam, fail-closed both ways the reference
     * crashed: an unsafe method without a credential is refused
     * before the boundary runs, and an `X-Replay-Id` longer than the
     * request value object's own bound is refused as the replay
     * rejection it is -- null when the input carries no refusal.
     */
    private function refuseInput(string $method, ?AuthCredential $credential, string $replayId): ?AuthRefusal
    {
        if (!\in_array($method, self::SAFE_METHODS, true) && !$credential instanceof AuthCredential) {
            return AuthRefusal::unauthenticated();
        }

        if ($replayId !== '' && mb_strlen($replayId) > AuthRequest::MAX_REPLAY_ID_CHARS) {
            return AuthRefusal::for(AuthFailure::REPLAY_REJECTED);
        }

        return null;
    }
}
