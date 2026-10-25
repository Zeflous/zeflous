<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The session family's outbound walk: the seam that answers what the
 * storage and the `Set-Cookie` header owe for the session the
 * handler leaves behind -- destruction, issuance, or the write-only
 * refresh.
 *
 * The three verdicts, in order: a destroyed session
 * ({@see SessionState::destroy()}) destroys the storage entry and
 * answers the deletion cookie -- including the freshly-minted case,
 * because the client that carried a now-expired cookie (the very
 * reason the session was minted fresh) still holds a stale cookie
 * the deletion retires; a freshly minted session the handler WROTE
 * INTO saves its bag and answers the issuance cookie carrying the
 * new identifier -- the client must learn the identifier before it
 * can ever carry it -- while a freshly minted session the handler
 * never wrote answers neither, because minting alone is not state
 * worth persisting: the cookie-less traffic (crawler, attacker,
 * private browsing) that mints and never returns would otherwise
 * grow the storage one abandoned empty entry per request (the
 * round-3 audit's R3-4); and a live session saves only when the bag
 * carries writes, the write-only refresh the family's idle doctrine
 * spells: the idle clock is the storage's own last-write clock, so a
 * request that reads without writing does not extend the session,
 * and the read-only touch the alternative doctrine would answer with
 * a storage write per request is a knob the family grows when a
 * consumer asks for it, not a posture it assumes silently.
 *
 * The cookie line is appended beside whatever `Set-Cookie` values
 * the handler already set ({@see CsrfMiddleware}'s own note: RFC
 * 6265 responses routinely carry several, and a session issuance
 * must never cost the handler its CSRF or login cookie), rendered
 * over the request's own scheme through the secure-cookie builder
 * ({@see SetCookie}) with the policy's flags -- the roadmap's
 * promised secure defaults riding on every line the family answers.
 */
final readonly class SessionSettlement
{
    public function __construct(
        private SessionPolicy $sessionPolicy,
        private SessionAdapterInterface $sessionAdapter,
    ) {
    }

    /**
     * Answers the response the session's verdict decorates.
     *
     * @param ResponseInterface      $response      the handler's answer
     * @param SessionState           $sessionState  the session the request
     *                                              leaves behind
     * @param ServerRequestInterface $serverRequest the request whose scheme
     *                                              the cookie's Secure
     *                                              emission rides
     */
    public function settle(
        ResponseInterface $response,
        SessionState $sessionState,
        ServerRequestInterface $serverRequest,
    ): ResponseInterface {
        if ($sessionState->isDestroyed()) {
            $this->sessionAdapter->destroy($sessionState->id());

            return $this->withCookie(
                $response,
                SetCookie::deleted(
                    $this->sessionPolicy->cookieName,
                    $this->sessionPolicy->sameSite,
                    $this->sessionPolicy->cookieSecure,
                    $this->sessionPolicy->cookieHttpOnly,
                ),
                $serverRequest,
            );
        }

        if ($sessionState->isNew()) {
            if (!$sessionState->isDirty()) {
                // A mint the handler never wrote into is an identifier
                // the client never needs to learn: no storage entry,
                // no issuance cookie -- minting alone is not state.
                return $response;
            }

            $this->sessionAdapter->save($sessionState->id(), $sessionState->data());

            return $this->withCookie(
                $response,
                new SetCookie(
                    name: $this->sessionPolicy->cookieName,
                    value: $sessionState->id(),
                    sameSite: $this->sessionPolicy->sameSite,
                    secure: $this->sessionPolicy->cookieSecure,
                    httpOnly: $this->sessionPolicy->cookieHttpOnly,
                ),
                $serverRequest,
            );
        }

        if ($sessionState->isDirty()) {
            $this->sessionAdapter->save($sessionState->id(), $sessionState->data());
        }

        return $response;
    }

    /**
     * The cookie decoration: one `Set-Cookie` value appended beside
     * whatever the handler already set, rendered over the request's
     * own scheme.
     */
    private function withCookie(
        ResponseInterface $response,
        SetCookie $setCookie,
        ServerRequestInterface $serverRequest,
    ): ResponseInterface {
        $message = $response->withAddedHeader(
            'Set-Cookie',
            $setCookie->headerValue($serverRequest->getUri()->getScheme()),
        );

        \assert($message instanceof ResponseInterface, 'the wither preserves the response type.');

        return $message;
    }
}
