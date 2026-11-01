<?php

declare(strict_types=1);

namespace Zef\Framework;

/**
 * The registration phase's claim ledger: the one map the boot's registrars
 * share, recording which module claimed which service identifier.
 *
 * The ledger is the two-phase boot's own answer to the duplicate-claim
 * question -- a registrar alone cannot tell "another module claimed this"
 * from "the host's boot already carries this", because a module's claim
 * flows into the container the moment it is made. The ledger sits beside
 * the container for the one phase registrations run, and the refusal it
 * feeds names the first claimant -- the sentence the second claimant's
 * author needs.
 */
final class ModuleClaims
{
    /** @var array<string, string> claimed identifier to the claiming module */
    private array $claimed = [];

    /**
     * The module that claimed the identifier, or null when none did.
     */
    public function claimant(string $id): ?string
    {
        return $this->claimed[$id] ?? null;
    }

    /**
     * Records the identifier's claim for the module.
     */
    public function claim(string $id, string $module): void
    {
        $this->claimed[$id] = $module;
    }
}
