<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Override;
use Random\RandomException;
use Zef\Framework\Adapters\Redis\RespConnection;
use Zef\Framework\Adapters\Redis\RespReplyShape;
use Zef\Framework\Event\DeferredDeliveryStore;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryEnvelopeDecoder;
use Zef\Framework\Event\DeliveryEnvelopeEncoder;
use Zef\Framework\Event\DrainTrace;
use Zef\Framework\Event\ListenerIdentity;

/**
 * The deferred delivery store's cross-process answer: the Redis
 * server's own reply to the persistence port -- the first of the
 * bus transports the roadmap's cross-process phase names, over the
 * same port the file adapter answered, the record spelling the Event
 * lane's encoder already owns (the record is the same JSON either
 * side of the store choice). The pending index is a sorted set whose
 * members are the entry identities and whose scores are their
 * ready-at moments -- the index the file adapter's honesty ledger
 * names, answering the due selection without reading every record;
 * the exclusivity the file adapter declared absent is the claim key
 * the claim fold arms with its own expiry ({@see RedisDeliveryClaim});
 * the dead-letter lane is one record per spent entry keyed by its
 * own identity, the same story rewritten is the same record replaced.
 *
 * The protocol the port spells lands here as the command orderings
 * it takes: {@see takeDue()} claims the due entries and reads their
 * records (an entry claimed but not settled is re-taken by the next
 * pass of its own drainer, and by any drainer after the claim's
 * expiry -- the crash window that costs a redelivery, never a loss),
 * {@see settle()} deletes the record BEFORE the index member (the
 * crash between the two is the zombie member whose next claim
 * answers no record, completed quietly by the records walk), and
 * {@see requeue()} writes the retry's record before its index member
 * (the crash between the two strands an orphan record no verb reads
 * -- inert by construction, the index is the only door -- never a
 * half-admitted entry a drain would have to guess at) -- with the
 * regime's BOOKS and JOURNAL answered by their own fold
 * ({@see RedisDeliveryBooks}): the attribution hash, the
 * quarantine set and the drain list, the same wire discipline
 * carried onto their commands.
 */
final readonly class RedisDeliveryStore implements DeferredDeliveryStore
{
    private RespReplyShape $respReplyShape;

    private DeliveryEnvelopeEncoder $deliveryEnvelopeEncoder;

    private RedisDeliveryKeys $redisDeliveryKeys;

    private RedisDeliveryAdmission $redisDeliveryAdmission;

    private RedisDeliveryClaim $redisDeliveryClaim;

    private RedisDeliveryClaimedRecords $redisDeliveryClaimedRecords;

    private RedisDeliveryBooks $redisDeliveryBooks;

    private string $drainerToken;

    /**
     * @param RespConnection $respConnection      the wire to the shared server -- one
     *                                            connection, one lifetime, the reconnect policy the
     *                                            deployment's own glue owns
     * @param int            $capacity            the pending bound (>= 1)
     * @param int            $claimTimeoutSeconds the visibility timeout (>= 1) a
     *                                            crashed drainer's claims survive for
     * @param string         $prefix              the store's key namespace inside the shared
     *                                            server
     *
     * @throws InvalidArgumentException when the capacity or the
     *                                  visibility timeout is below one, or the prefix refuses
     * @throws RandomException          when the engine's random source
     *                                  refuses to answer the drainer's token
     */
    public function __construct(
        private RespConnection $respConnection,
        int $capacity,
        int $claimTimeoutSeconds,
        string $prefix,
    ) {
        $this->respReplyShape = new RespReplyShape();
        $this->deliveryEnvelopeEncoder = new DeliveryEnvelopeEncoder();
        $this->drainerToken = DeliveryEntryId::mint()->spelling();
        $this->redisDeliveryKeys = new RedisDeliveryKeys($prefix);
        $this->redisDeliveryBooks = new RedisDeliveryBooks($respConnection, $this->redisDeliveryKeys);
        $this->redisDeliveryAdmission = new RedisDeliveryAdmission(
            $respConnection,
            $this->redisDeliveryKeys,
            $this->deliveryEnvelopeEncoder,
            $capacity,
        );
        $this->redisDeliveryClaim = new RedisDeliveryClaim(
            $respConnection,
            $this->redisDeliveryKeys,
            $this->drainerToken,
            $claimTimeoutSeconds,
        );
        $this->redisDeliveryClaimedRecords = new RedisDeliveryClaimedRecords(
            $respConnection,
            $this->redisDeliveryKeys,
            new DeliveryEnvelopeDecoder(),
        );
    }

    #[Override]
    public function save(DeliveryEnvelope ...$envelopes): void
    {
        $this->redisDeliveryAdmission->admit(...$envelopes);
    }

    #[Override]
    public function requeue(DeliveryEnvelope $deliveryEnvelope): void
    {
        $entryId = $deliveryEnvelope->entryId()->spelling();

        $this->respReplyShape->status(
            $this->respConnection->command(
                'SET',
                $this->redisDeliveryKeys->entryKey($entryId),
                $this->deliveryEnvelopeEncoder->encode($deliveryEnvelope),
            ),
            'SET',
        );

        $this->respReplyShape->integer(
            $this->respConnection->command(
                'ZADD',
                $this->redisDeliveryKeys->pendingKey(),
                $deliveryEnvelope->readyAt(),
                $entryId,
            ),
            'ZADD',
        );
    }

    #[Override]
    public function takeDue(int $now): array
    {
        return $this->redisDeliveryClaimedRecords->answeredDeliveries(
            $this->redisDeliveryClaim->claimDue($now),
        );
    }

    #[Override]
    public function settle(DeliveryEnvelope ...$envelopes): void
    {
        foreach ($envelopes as $envelope) {
            $entryId = $envelope->entryId()->spelling();

            $this->respReplyShape->integer(
                $this->respConnection->command(
                    'DEL',
                    $this->redisDeliveryKeys->entryKey($entryId),
                ),
                'DEL',
            );

            $this->respReplyShape->integer(
                $this->respConnection->command(
                    'ZREM',
                    $this->redisDeliveryKeys->pendingKey(),
                    $entryId,
                ),
                'ZREM',
            );
        }
    }

    #[Override]
    public function deadLetter(
        DeliveryEnvelope $deliveryEnvelope,
        string $failureClass,
        string $failureMessage,
        int $deadAt,
    ): void {
        $this->respReplyShape->status(
            $this->respConnection->command(
                'SET',
                $this->redisDeliveryKeys->deadKey(
                    $deliveryEnvelope->entryId()->spelling(),
                ),
                $this->deliveryEnvelopeEncoder->encodeDeadLetter(
                    $deliveryEnvelope,
                    $failureClass,
                    $failureMessage,
                    $deadAt,
                ),
            ),
            'SET',
        );
    }

    #[Override]
    public function pendingCount(): int
    {
        return $this->respReplyShape->integer(
            $this->respConnection->command(
                'ZCARD',
                $this->redisDeliveryKeys->pendingKey(),
            ),
            'ZCARD',
        );
    }

    #[Override]
    public function recordAttributedDeath(
        DeliveryEntryId $deliveryEntryId,
        ListenerIdentity $listenerIdentity,
        int $attributedAt,
    ): void {
        $this->redisDeliveryBooks->recordAttributedDeath($deliveryEntryId, $listenerIdentity);
    }

    #[Override]
    public function deadLettersAttributedTo(ListenerIdentity $listenerIdentity): int
    {
        return $this->redisDeliveryBooks->deadLettersAttributedTo($listenerIdentity);
    }

    #[Override]
    public function quarantineListener(
        ListenerIdentity $listenerIdentity,
        int $quarantinedAt,
    ): void {
        $this->redisDeliveryBooks->quarantineListener($listenerIdentity);
    }

    #[Override]
    public function isListenerQuarantined(ListenerIdentity $listenerIdentity): bool
    {
        return $this->redisDeliveryBooks->isListenerQuarantined($listenerIdentity);
    }

    #[Override]
    public function recordDrainOutcome(DrainTrace $drainTrace): void
    {
        $this->redisDeliveryBooks->recordDrainOutcome($drainTrace);
    }
}
