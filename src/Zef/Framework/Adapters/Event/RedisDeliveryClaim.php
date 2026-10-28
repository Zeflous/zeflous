<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Adapters\Redis\RespConnection;
use Zef\Framework\Adapters\Redis\RespReplyListShape;
use Zef\Framework\Adapters\Redis\RespReplyShape;

/**
 * The Redis-backed delivery store's exclusivity fold -- the claim
 * semantics the file adapter's own honesty ledger names as the
 * cross-process answer's business: which due entries THIS drainer
 * holds exclusively. The pending index answers the due identities
 * (the sorted set's own order -- ready-at then entry identity, the
 * ordering doctrine the index carries for free); each due entry is
 * then claimed by an atomic write that only succeeds when no other
 * drainer holds it, the claim carrying its own expiry -- the
 * visibility timeout that bounds a crashed drainer's redelivery
 * window; and a claim this store already holds is re-taken, because
 * the port's own promise is that an entry taken but not settled is
 * re-taken by the next pass -- the duplicate the at-least-once
 * doctrine owns, never a silent skip of this drainer's own work.
 */
final readonly class RedisDeliveryClaim
{
    private RespReplyListShape $respReplyListShape;

    private RespReplyShape $respReplyShape;

    /**
     * @param string $drainerToken        this store instance's own identity -- the
     *                                    spelling a claim answers when this drainer holds it
     * @param int    $claimTimeoutSeconds the visibility timeout (>= 1): how long a
     *                                    claim survives its drainer before another may take the
     *                                    entry again
     *
     * @throws InvalidArgumentException when the visibility timeout is
     *                                  below one second -- a claim shorter than a second is not
     *                                  a delivery window
     */
    public function __construct(
        private RespConnection $respConnection,
        private RedisDeliveryKeys $redisDeliveryKeys,
        private string $drainerToken,
        private int $claimTimeoutSeconds,
    ) {
        if ($this->claimTimeoutSeconds < 1) {
            throw new InvalidArgumentException(
                "A delivery claim's visibility timeout must be at least one"
                . ' second, got ' . $this->claimTimeoutSeconds . '.',
            );
        }

        $this->respReplyListShape = new RespReplyListShape();
        $this->respReplyShape = new RespReplyShape();
    }

    /**
     * The due entries this drainer holds exclusively at the given
     * now.
     *
     * @return list<string> the entry identities won -- the due order
     *                      the index answers
     */
    public function claimDue(int $now): array
    {
        $due = $this->respReplyListShape->bulkList(
            $this->respConnection->command(
                'ZRANGEBYSCORE',
                $this->redisDeliveryKeys->pendingKey(),
                '-inf',
                $now,
            ),
            'ZRANGEBYSCORE',
        );

        $won = [];

        foreach ($due as $entryId) {
            if (!$this->isClaimed($entryId)) {
                continue;
            }

            $won[] = $entryId;
        }

        return $won;
    }

    /**
     * Whether this drainer holds the claim on one due entry: the
     * atomic write that only succeeds unclaimed, and the re-take of
     * the claim this store already holds.
     */
    private function isClaimed(string $entryId): bool
    {
        $claimReply = $this->respConnection->command(
            'SET',
            $this->redisDeliveryKeys->claimKey($entryId),
            $this->drainerToken,
            'NX',
            'PX',
            $this->claimTimeoutSeconds * 1000,
        );

        if ($claimReply !== null) {
            $this->respReplyShape->status($claimReply, 'SET');

            return true;
        }

        $holder = $this->respReplyShape->bulk(
            $this->respConnection->command(
                'GET',
                $this->redisDeliveryKeys->claimKey($entryId),
            ),
            'GET',
        );

        return $holder === $this->drainerToken;
    }
}
