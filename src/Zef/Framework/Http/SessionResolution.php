<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The session family's inbound walk: the one seam that answers the
 * request's session -- the cookie the client carried, the identifier
 * grammar, the storage's own verdict, and the fresh mint when no
 * carried cookie matches a live session.
 *
 * The walk is cheapest-refusal-first, exactly as the body-parser
 * family's reader walks: the carried value is read through the
 * request's own cookie bag ({@see RequestCookies::value()}); a name
 * the bag does not carry answers a freshly minted session before any
 * storage is asked; a value outside the identifier grammar
 * ({@see SessionId::isValid()}) is treated as an absent one -- a
 * stale, truncated or hand-mangled cookie is a client condition every
 * deployment meets, answered with a fresh session rather than a
 * server-side refusal; and only then does the adapter load, whose
 * null (unknown, or idle timeout fully elapsed) answers the fresh
 * mint too. The storage is never asked about a value the grammar
 * already refused, and the walk never distinguishes an expired
 * session from an unknown one -- the two are the same answer to
 * every consumer ({@see SessionAdapterInterface}'s own note).
 */
final readonly class SessionResolution
{
    public function __construct(
        private SessionPolicy $sessionPolicy,
        private SessionId $sessionId,
        private SessionAdapterInterface $sessionAdapter,
    ) {
    }

    /**
     * Answers the request's session state: the loaded bag when a
     * carried identifier matches a live session, or a freshly minted
     * empty one.
     */
    public function resolve(ServerRequestInterface $serverRequest): SessionState
    {
        $carriedId = RequestCookies::value($serverRequest, $this->sessionPolicy->cookieName);

        if ($carriedId !== null && SessionId::isValid($carriedId)) {
            $data = $this->sessionAdapter->load($carriedId);

            if ($data !== null) {
                return new SessionState($carriedId, $data);
            }
        }

        return new SessionState($this->sessionId->issue(), [], isNew: true);
    }
}
