<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The `Set-Cookie` line the session's verdict answers, lifted into
 * its own judgment unit (the #801 round's own archeology answer: the
 * settlement's difficulty crossed the bar with the rotation's
 * issuance arm aboard, and the cookie line -- deletion, issuance, or
 * nothing -- is the cohesive piece that moves out with the storage
 * walk, the settlement keeping the composition alone).
 *
 * The line the verdict answers: a destroyed session answers the
 * deletion cookie -- including the freshly-minted case, because the
 * client that carried a now-expired cookie (the very reason the
 * session was minted fresh) still holds a stale cookie the deletion
 * retires, and including a rotation the destruction follows; a bag
 * the handler WROTE INTO under a freshly minted identifier (or a
 * freshly rotated one -- issue #801's finding 6, the client must
 * learn the identifier before it can ever carry it) answers the
 * issuance cookie carrying it; and every other verdict answers
 * nothing, because minting alone is not state worth persisting: the
 * cookie-less traffic (crawler, attacker, private browsing) that
 * mints and never returns would otherwise grow the storage one
 * abandoned empty entry per request (the round-3 audit's R3-4).
 */
final class SessionCookieLine
{
    /**
     * The `Set-Cookie` value the verdict answers, or null when the
     * answer owes no line at all (the live session the handler never
     * wrote -- the client already carries the identifier -- and the
     * mint the handler never wrote into).
     */
    public static function verdict(SessionPolicy $sessionPolicy, SessionState $sessionState): ?SetCookie
    {
        if ($sessionState->isDestroyed()) {
            return SetCookie::deleted(
                $sessionPolicy->cookieName,
                $sessionPolicy->sameSite,
                $sessionPolicy->cookieSecure,
                $sessionPolicy->cookieHttpOnly,
            );
        }

        if ($sessionState->isDirty() && ($sessionState->isNew() || $sessionState->isRegenerated())) {
            return new SetCookie(
                name: $sessionPolicy->cookieName,
                value: $sessionState->id(),
                sameSite: $sessionPolicy->sameSite,
                secure: $sessionPolicy->cookieSecure,
                httpOnly: $sessionPolicy->cookieHttpOnly,
            );
        }

        return null;
    }
}
