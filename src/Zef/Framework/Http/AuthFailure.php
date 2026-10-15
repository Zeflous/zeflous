<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The admission failure taxonomy: why the boundary refused, the
 * vocabulary the boundary composes into {@see AuthAdmission},
 * move-verbatim from the reference's distributed security family
 * (legacy `zef-main` `Framework/Security/Distributed/
 * SecurityFailure.php`).
 *
 * The taxonomy is the boundary's internal answer key -- the client
 * only ever sees the status code and reason phrase it maps to, never
 * the value itself: the reference echoed the failure value into the
 * refusal body (`authentication_failed` and friends), and this port
 * deliberately does not ({@see AuthMiddleware} -- the layer's
 * disclosure discipline keeps the envelope's message to the public
 * reason phrase, because an anonymous client has no business learning
 * which internal check refused it; the status code already carries
 * every signal the client can act on).
 *
 * `MALFORMED_METADATA` is contract vocabulary only: the
 * authenticated-without-context result it answered in the reference is
 * unreachable through {@see AuthResult}'s constructor invariant in
 * both implementations, so no boundary arm produces it -- it stays in
 * the enum the way `STALE` stays in {@see ReplayDecision}, as the
 * closed vocabulary an application-supplied boundary arm could
 * legitimately answer.
 */
enum AuthFailure: string
{
    case NONE = 'none';

    case AUTHENTICATION_FAILED = 'authentication_failed';

    case AUTHENTICATION_UNAVAILABLE = 'authentication_unavailable';

    case CREDENTIAL_EXPIRED = 'credential_expired';

    case AUTHORIZATION_DENIED = 'authorization_denied';

    case REPLAY_REJECTED = 'replay_rejected';

    case REPLAY_UNAVAILABLE = 'replay_unavailable';

    case MALFORMED_METADATA = 'malformed_metadata';

    /**
     * The failure a non-authenticated status earns: the expiry the
     * client can act on, the outage the client cannot, and the wrong
     * or unvouched credential the reference folded into one answer
     * -- the boundary's own order, spelled by the taxonomy it
     * refuses over.
     */
    public static function unauthenticated(AuthStatus $authStatus): self
    {
        return match ($authStatus) {
            AuthStatus::EXPIRED => self::CREDENTIAL_EXPIRED,
            AuthStatus::UNAVAILABLE => self::AUTHENTICATION_UNAVAILABLE,
            default => self::AUTHENTICATION_FAILED,
        };
    }

    /**
     * The failure a non-allowing replay decision earns: the table's
     * unavailability is the outage the client cannot fix, every other
     * refusal the rejection the boundary owns.
     */
    public static function replay(ReplayDecision $replayDecision): self
    {
        return $replayDecision === ReplayDecision::UNAVAILABLE
            ? self::REPLAY_UNAVAILABLE
            : self::REPLAY_REJECTED;
    }
}
