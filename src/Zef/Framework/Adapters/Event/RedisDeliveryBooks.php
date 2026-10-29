<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use Zef\Framework\Adapters\Redis\RespConnection;
use Zef\Framework\Adapters\Redis\RespReplyListShape;
use Zef\Framework\Adapters\Redis\RespReplyShape;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DrainTrace;
use Zef\Framework\Event\DrainTraceEncoder;
use Zef\Framework\Event\ListenerIdentity;

/**
 * The Redis-backed delivery store's books and journal: the
 * attribution index, the quarantine set and the drain list -- the
 * durable re-spelling of the process-local books, answered over
 * the same wire the entry lifecycle spells. The attribution index
 * is a hash whose fields ARE the attributed entries' own
 * identities and whose values ARE the listener identities their
 * deaths belong to, so the same entry attributed twice (the
 * at-least-once duplicate) rewrites the same field, never doubles
 * the book; the quarantine set's members ARE the quarantined
 * identities, the containment idempotent by the set's own
 * semantics; the journal is the list the completed passes append
 * their traces to, in the order the passes completed -- the Event
 * lane's own encoder spelling the record, the same bytes the file
 * journal writes.
 *
 * Every reply is narrowed to the shape its command's grammar
 * answers, the wrong shape refused with both shapes named -- the
 * wire family's own discipline, carried onto the books' commands.
 */
final readonly class RedisDeliveryBooks
{
    private RespReplyShape $respReplyShape;

    private RespReplyListShape $respReplyListShape;

    private DrainTraceEncoder $drainTraceEncoder;

    public function __construct(
        private RespConnection $respConnection,
        private RedisDeliveryKeys $redisDeliveryKeys,
    ) {
        $this->respReplyShape = new RespReplyShape();
        $this->respReplyListShape = new RespReplyListShape();
        $this->drainTraceEncoder = new DrainTraceEncoder();
    }

    /**
     * Attributes one spent entry's death to the listener's identity
     * -- one hash field written, keyed by the entry's own identity.
     * The attribution moment is deliberately not the index's to
     * carry: the hash is the book the threshold reads, not the
     * story an operator reads -- the dead record the same entry
     * wrote carries the whole of that story.
     */
    public function recordAttributedDeath(
        DeliveryEntryId $deliveryEntryId,
        ListenerIdentity $listenerIdentity,
    ): void {
        $this->respReplyShape->integer(
            $this->respConnection->command(
                'HSET',
                $this->redisDeliveryKeys->attributedKey(),
                $deliveryEntryId->spelling(),
                $listenerIdentity->spelling(),
            ),
            'HSET',
        );
    }

    /**
     * How many attributed deaths the index holds for the listener's
     * identity -- the values walk, counted by the spelling the
     * identity's own grammar answers.
     */
    public function deadLettersAttributedTo(ListenerIdentity $listenerIdentity): int
    {
        $attributed = 0;

        foreach (
            $this->respReplyListShape->bulkList(
                $this->respConnection->command(
                    'HVALS',
                    $this->redisDeliveryKeys->attributedKey(),
                ),
                'HVALS',
            ) as $identity
        ) {
            if ($identity !== $listenerIdentity->spelling()) {
                continue;
            }

            ++$attributed;
        }

        return $attributed;
    }

    /**
     * Records the containment act -- the identity joined to the
     * quarantine set, idempotent by the set's own semantics. The
     * quarantine moment is deliberately not the set's to carry:
     * the membership IS the containment, and the operator who
     * restores a listener removes the member, never consults a
     * timestamp the set never promised.
     */
    public function quarantineListener(
        ListenerIdentity $listenerIdentity,
    ): void {
        $this->respReplyShape->integer(
            $this->respConnection->command(
                'SADD',
                $this->redisDeliveryKeys->quarantineKey(),
                $listenerIdentity->spelling(),
            ),
            'SADD',
        );
    }

    /**
     * Whether the identity is quarantined -- the set's own
     * membership, the filter every future defer reads.
     */
    public function isListenerQuarantined(ListenerIdentity $listenerIdentity): bool
    {
        return $this->respReplyShape->integer(
            $this->respConnection->command(
                'SISMEMBER',
                $this->redisDeliveryKeys->quarantineKey(),
                $listenerIdentity->spelling(),
            ),
            'SISMEMBER',
        ) === 1;
    }

    /**
     * Appends one completed pass's trace to the journal list -- the
     * Event lane's own encoder spelling the record.
     */
    public function recordDrainOutcome(DrainTrace $drainTrace): void
    {
        $this->respReplyShape->integer(
            $this->respConnection->command(
                'RPUSH',
                $this->redisDeliveryKeys->drainsKey(),
                $this->drainTraceEncoder->encode($drainTrace),
            ),
            'RPUSH',
        );
    }
}
