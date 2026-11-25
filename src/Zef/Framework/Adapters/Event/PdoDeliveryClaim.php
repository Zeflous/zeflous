<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Database\ConditionComparison;
use Zef\Framework\Database\ConditionGroup;
use Zef\Framework\Database\ConditionNull;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\IdentifierQuoting;
use Zef\Framework\Database\QueryBuilder;

/**
 * The PDO-backed delivery store's exclusivity fold -- the claim
 * semantics the file adapter's own honesty ledger names as the
 * cross-process answer's business, spelled as the two claim columns
 * the delivery row owns: which due entries THIS drainer holds
 * exclusively. The due identities arrive from the due-scan fold;
 * each due entry is then claimed by ONE conditional UPDATE whose
 * WHERE is the whole exclusivity contract -- unclaimed, or this
 * drainer's own claim (the port's promise that an entry taken but
 * not settled is re-taken by the next pass), or a claim whose
 * visibility timeout has handed the entry to the next drainer --
 * so a foreign live claim is respected by the engine itself, the
 * row never read into a state another drainer owns, and the
 * affected-rows answer is the verdict: one row written is the
 * claim won, zero rows is a claim another drainer holds.
 */
final readonly class PdoDeliveryClaim
{
    private IdentifierQuoting $identifierQuoting;

    /**
     * @param string $drainerToken        this store instance's own identity -- the
     *                                    spelling the claim column answers when this
     *                                    drainer holds it
     * @param int    $claimTimeoutSeconds the visibility timeout (>= 1): how many
     *                                    seconds a claim survives its drainer before
     *                                    another may take the entry again
     *
     * @throws InvalidArgumentException when the visibility timeout is
     *                                  below one second -- a claim shorter than a
     *                                  second is not a delivery window
     */
    public function __construct(
        private ConnectionInterface $connection,
        private PdoDeliveryTables $pdoDeliveryTables,
        private PdoDeliveryDueScan $pdoDeliveryDueScan,
        private string $drainerToken,
        private int $claimTimeoutSeconds,
    ) {
        if ($this->claimTimeoutSeconds < 1) {
            throw new InvalidArgumentException(
                "A delivery claim's visibility timeout must be at least one"
                . ' second, got ' . $this->claimTimeoutSeconds . '.',
            );
        }

        $this->identifierQuoting = IdentifierQuoting::forDriver($connection->driver());
    }

    /**
     * The due entries this drainer holds exclusively at the given
     * now.
     *
     * @param int $now the drain's own now, the seconds the regime spells
     *
     * @return list<string> the entry identities won -- the due order
     *                      the index answers
     */
    public function claimDue(int $now): array
    {
        $won = [];

        foreach ($this->pdoDeliveryDueScan->dueIdentities($now) as $entryId) {
            if ($this->claim($entryId, $now)) {
                $won[] = $entryId;
            }
        }

        return $won;
    }

    /**
     * Whether this drainer holds the claim on one due entry: the
     * conditional UPDATE whose WHERE is the exclusivity contract,
     * the affected-rows answer the verdict.
     */
    private function claim(string $entryId, int $now): bool
    {
        $affected = $this->connection->execute(
            QueryBuilder::table(
                $this->pdoDeliveryTables->deliveryTable()->name,
                null,
                $this->identifierQuoting,
            )
                ->update([
                    'claim_token' => $this->drainerToken,
                    'claim_expires_at' => $now + $this->claimTimeoutSeconds,
                ])
                ->where('entry_id', '=', $entryId)
                ->where(ConditionGroup::any(
                    new ConditionNull('claim_token'),
                    new ConditionComparison('claim_token', '=', $this->drainerToken),
                    new ConditionComparison('claim_expires_at', '<=', $now),
                ))
                ->build(),
        );

        return $affected === 1;
    }
}
