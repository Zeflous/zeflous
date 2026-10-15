<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The replay protection's decisions: what a replay protector answered
 * about the replay id it was handed, move-verbatim from the
 * reference's distributed security family (legacy `zef-main`
 * `Framework/Security/Distributed/ReplayDecision.php`), with the
 * reference's {@see ReplayResult} wrapper folded onto the enum itself
 * ({@see self::allows()}) the way the CSRF family folded its
 * SameSite vocabulary -- the wrapper carried nothing but the decision
 * and one predicate over it.
 *
 * `STALE` is contract vocabulary the in-memory protector never
 * answers (a timestamp-window protector would): the boundary maps it
 * to the same rejection as a duplicate, and the vocabulary stays
 * closed for application-supplied protectors.
 */
enum ReplayDecision: string
{
    case NOT_REQUIRED = 'not_required';

    case ACCEPT = 'accept';

    case DUPLICATE = 'duplicate';

    case STALE = 'stale';

    case REJECTED = 'rejected';

    case UNAVAILABLE = 'unavailable';

    /**
     * Whether the decision admits the request: the two answers a
     * boundary treats as passage -- no replay id carried (nothing to
     * protect) or the id accepted as fresh.
     */
    public function allows(): bool
    {
        return $this === self::NOT_REQUIRED || $this === self::ACCEPT;
    }
}
