<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The authorization verdict: what a policy answered about the request
 * it was asked to authorise, move-verbatim from the reference's
 * distributed security family (legacy `zef-main`
 * `Framework/Security/Distributed/SecurityVerdict.php`).
 *
 * The vocabulary is closed on purpose: the boundary composes this
 * verdict into its admission decision ({@see AuthAdmission}), and a
 * third spelling (`ABSTAIN`-style) would only move the tie-break
 * somewhere the caller cannot see it. A policy that cannot decide is
 * a policy that denies.
 */
enum AuthVerdict: string
{
    case ALLOW = 'allow';

    case DENY = 'deny';
}
