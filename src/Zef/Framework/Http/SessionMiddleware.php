<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The session family's PSR-15 boundary: the request decorated with
 * its session bag, the response decorated with the cookie line the
 * session's verdict owes -- the roadmap's Phase 3.3 session and
 * cookie abstraction, over the resolution walk ({@see
 * SessionResolution}) and the settlement walk ({@see
 * SessionSettlement}): the boundary's own questions are the family's
 * arm and the two decorations, with both walks themselves the
 * extracted seams' -- the three-seam discipline the body-parser
 * family taught, kept ahead of the architecture gate's difficulty
 * ceiling instead of behind it.
 *
 * The family's arm is the timing family's inert posture: a disabled
 * family passes the request through untouched -- no cookie is read,
 * no storage is asked, no attribute is decorated -- because the
 * middleware stays in the stack only as the host's declaration, and
 * a host that disabled the family did not ask for a sessionless
 * request to pay for one.
 *
 * The decoration inbound is the attribute ({@see
 * self::SESSION_ATTRIBUTE}): the bag the controller pulls, writes
 * into, or destroys, without ever touching the adapter -- the whole
 * promise of the port. The decoration outbound is the settlement's:
 * the storage verdict and the `Set-Cookie` line, secure defaults
 * riding ({@see SetCookie}).
 *
 * The adapter is injectable, defaulting to the in-memory driver over
 * the policy's own idle timeout: a deployment that prefers another
 * storage composes the middleware over its own driver
 * ({@see SessionAdapterInterface}) -- the swap the hexagonal promise
 * spells, a constructor write, never an application change.
 */
final readonly class SessionMiddleware implements MiddlewareInterface
{
    /** The request attribute the session bag rides: the one name the
     * controller reads the family through. */
    public const string SESSION_ATTRIBUTE = 'zef.http.session';

    private SessionResolution $sessionResolution;

    private SessionSettlement $sessionSettlement;

    /**
     * @param SessionPolicy                $sessionPolicy  the family's
     *                                                     frozen knob set
     * @param null|SessionAdapterInterface $sessionAdapter the storage
     *                                                     driver; defaults
     *                                                     to the in-memory
     *                                                     driver over the
     *                                                     policy's idle
     *                                                     timeout
     */
    public function __construct(
        private SessionPolicy $sessionPolicy,
        ?SessionAdapterInterface $sessionAdapter = null,
    ) {
        $adapter = $sessionAdapter ?? new InMemorySessionAdapter($sessionPolicy->idleTimeout);

        $this->sessionResolution = new SessionResolution(
            $sessionPolicy,
            new SessionId($sessionPolicy->idBytes),
            $adapter,
        );
        $this->sessionSettlement = new SessionSettlement($sessionPolicy, $adapter);
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (!$this->sessionPolicy->enabled) {
            return $handler->handle($request);
        }

        $sessionState = $this->sessionResolution->resolve($request);

        $response = $handler->handle(
            $request->withAttribute(self::SESSION_ATTRIBUTE, $sessionState),
        );

        return $this->sessionSettlement->settle($response, $sessionState, $request);
    }
}
