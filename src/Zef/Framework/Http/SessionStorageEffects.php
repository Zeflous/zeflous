<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The storage side-effects the session's verdict owes: the entries
 * that die and the bag that saves, lifted into their own unit (the
 * #801 round's own archeology answer -- the settlement's difficulty
 * crossed the bar with the rotation's sweep aboard, and the storage
 * walk is the cohesive piece that moves out, the settlement keeping
 * the cookie line and the composition alone).
 *
 * The walk, in order: a destroyed session's current entry dies
 * beside the retired one a rotation had already marked (a
 * destruction following a logout-after-login rotation must leave no
 * entry alive -- the rebind-shaped sweep the one verdict owns
 * whole); a rotated-but-live session's retired entry dies alone (the
 * bag then saving under the fresh identifier, the identifier's
 * history retiring before its content moves -- issue #801's finding
 * 6); and a dirty bag saves under the identifier it currently rides.
 * The walk never throws and never answers anything: it is the
 * adapter's own choreography, the cookie line
 * ({@see SessionCookieLine}) deciding what the response carries.
 */
final readonly class SessionStorageEffects
{
    public function __construct(private SessionAdapterInterface $sessionAdapter)
    {
    }

    /**
     * Applies the storage side-effects the verdict owes.
     */
    public function apply(SessionState $sessionState): void
    {
        $retiredId = $sessionState->retiredId();

        if ($sessionState->isDestroyed()) {
            $this->sessionAdapter->destroy($sessionState->id());

            if ($retiredId !== null) {
                $this->sessionAdapter->destroy($retiredId);
            }

            return;
        }

        if ($sessionState->isRegenerated() && $retiredId !== null) {
            $this->sessionAdapter->destroy($retiredId);
        }

        if (!$sessionState->isDirty()) {
            return;
        }

        $this->sessionAdapter->save($sessionState->id(), $sessionState->data());
    }
}
