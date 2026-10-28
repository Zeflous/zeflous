<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Throwable;
use Zef\Framework\Adapters\Redis\RespConnection;
use Zef\Framework\Adapters\Redis\RespReplyShape;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryEnvelopeEncoder;

/**
 * The Redis-backed delivery store's admission fold: how a batch of
 * envelopes becomes pending entries -- the capacity backpressure
 * read from the pending index's own count (the whole batch or none,
 * the in-process arm's own doctrine, the counts spelled), then per
 * entry the record written first and the index member added second.
 * A refused mid-write batch is compensated whole, optimistically:
 * the compensation removes both halves of every entry the batch had
 * begun (a refused write may still have reached the server, and the
 * idempotent removal answers both halves of that ambiguity), and an
 * entry whose removal itself refuses strands -- the refusal names
 * the count, and the stranded entries stay pending, because they
 * are real work the next drain will deliver.
 */
final readonly class RedisDeliveryAdmission
{
    private RespReplyShape $respReplyShape;

    /**
     * @param int $capacity the pending bound (>= 1): the store keeps
     *                      at most this many pending entries
     *
     * @throws InvalidArgumentException when the capacity is below
     *                                  one -- a store that can hold nothing is not a store
     */
    public function __construct(
        private RespConnection $respConnection,
        private RedisDeliveryKeys $redisDeliveryKeys,
        private DeliveryEnvelopeEncoder $deliveryEnvelopeEncoder,
        private int $capacity,
    ) {
        if ($this->capacity < 1) {
            throw new InvalidArgumentException(
                "A delivery store's capacity must be at least one, got "
                . $this->capacity . '.',
            );
        }

        $this->respReplyShape = new RespReplyShape();
    }

    /**
     * Admits a batch of entries as one unit.
     *
     * @param DeliveryEnvelope ...$envelopes the entries one durable defer produced
     *
     * @throws InvalidArgumentException when the batch would push the
     *                                  store past its capacity, or the store cannot hold it whole
     */
    public function admit(DeliveryEnvelope ...$envelopes): void
    {
        $demanded = \count($envelopes);
        $occupied = $this->respReplyShape->integer(
            $this->respConnection->command(
                'ZCARD',
                $this->redisDeliveryKeys->pendingKey(),
            ),
            'ZCARD',
        );

        if ($occupied + $demanded > $this->capacity) {
            throw new InvalidArgumentException(
                'The delivery store is full: capacity ' . $this->capacity
                . ', occupied ' . $occupied . ', demanded ' . $demanded
                . ' -- drain before deferring more.',
            );
        }

        $begun = [];

        try {
            foreach ($envelopes as $envelope) {
                $begun[] = $envelope;

                $this->respReplyShape->status(
                    $this->respConnection->command(
                        'SET',
                        $this->redisDeliveryKeys->entryKey(
                            $envelope->entryId()->spelling(),
                        ),
                        $this->deliveryEnvelopeEncoder->encode($envelope),
                    ),
                    'SET',
                );

                $this->respReplyShape->integer(
                    $this->respConnection->command(
                        'ZADD',
                        $this->redisDeliveryKeys->pendingKey(),
                        $envelope->readyAt(),
                        $envelope->entryId()->spelling(),
                    ),
                    'ZADD',
                );
            }
        } catch (Throwable $throwable) {
            $this->compensate($begun);

            throw $throwable;
        }
    }

    /**
     * Removes both halves of every entry the refused batch had
     * begun -- the record and the index member, idempotent both.
     *
     * @param list<DeliveryEnvelope> $begun the entries the refused batch had begun
     */
    private function compensate(array $begun): void
    {
        $stranded = 0;

        foreach ($begun as $deliveryEnvelope) {
            try {
                $entryId = $deliveryEnvelope->entryId()->spelling();

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
            } catch (Throwable) {
                ++$stranded;
            }
        }

        if ($stranded > 0) {
            throw new InvalidArgumentException(
                'The refused durable delivery batch could not be compensated'
                . ' whole: ' . $stranded . ' begun entries stay in the store'
                . ' and will drain -- remove them deliberately, or let the'
                . ' drain answer them.',
            );
        }
    }
}
