<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The anonymous principal's reserved name: the one spelling a
 * credential-less resolution may answer, never a presented credential's
 * (issue #785's finding 4).
 *
 * The authorization policy finds the anonymous branch by folding the
 * context's principal id ({@see AllowScopePolicy}) -- the reference's
 * own case-insensitive comparison, a proxy the policy cannot escape
 * because it never sees the handle the credential travelled under. The
 * fold is therefore one vocabulary shared by every reader that must ask
 * the questions it spells: the policy's branch, the boundary's
 * reserved-name refusal ({@see AuthBoundary}) and the static table's
 * composition halt ({@see StaticCredentialProvider}) all hold the same
 * answer, so the fold stands as its own single-question unit exactly
 * the way the boot-graph's shadow judgment does ({@see
 * \Zef\Framework\Container\FactoryShadows::target()} -- the audit
 * lane's own idiom for a judgment three call sites share).
 */
final class ReservedPrincipalName
{
    /**
     * Whether a principal id claims the anonymous principal's reserved
     * name, the case fold the reference's own comparison holds.
     */
    public static function isClaimedBy(string $principalId): bool
    {
        return mb_strtolower($principalId) === 'anonymous';
    }

    /**
     * Whether a resolution claims the reserved name for a handle that
     * is not the anonymous resolution itself -- the boundary's own
     * question, the one fact the policy cannot see (which handle the
     * credential travelled under): a presented credential under the
     * reserved name would steer an authenticated principal into the
     * policy's anonymous branch, so the boundary refuses it as
     * malformed metadata. The anonymous handle keeps the name: it is
     * the one spelling a provider may legitimately answer `anonymous`
     * for.
     */
    public static function claimedBeyondTheAnonymousResolution(
        AuthCredential $authCredential,
        string $principalId,
    ): bool {
        return !$authCredential->isAnonymous() && self::isClaimedBy($principalId);
    }
}
