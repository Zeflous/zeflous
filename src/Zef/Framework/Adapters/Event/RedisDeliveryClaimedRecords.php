<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Adapters\Redis\RespConnection;
use Zef\Framework\Adapters\Redis\RespReplyMultiReadShape;
use Zef\Framework\Adapters\Redis\RespReplyShape;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryEnvelopeDecoder;

/**
 * The Redis-backed delivery store's records walk: the claimed
 * identities become the deliveries they spell -- one multi-read over
 * the entry records, each present record decoded fail-closed with
 * the entry named on any refusal (a record that decodes on a guess
 * is a delivery that was never admitted), and each absent record
 * recognised for what it is: the half of a settle that crashed after
 * the record was deleted and before the index member was removed --
 * a zombie member whose next claim answers no record, completed
 * quietly as the no-op the idempotent settle doctrine already
 * assumes, never delivered, never lost.
 */
final readonly class RedisDeliveryClaimedRecords
{
    private RespReplyMultiReadShape $respReplyMultiReadShape;

    private RespReplyShape $respReplyShape;

    public function __construct(
        private RespConnection $respConnection,
        private RedisDeliveryKeys $redisDeliveryKeys,
        private DeliveryEnvelopeDecoder $deliveryEnvelopeDecoder,
    ) {
        $this->respReplyMultiReadShape = new RespReplyMultiReadShape();
        $this->respReplyShape = new RespReplyShape();
    }

    /**
     * The deliveries the claimed identities spell, in the claim's
     * own order.
     *
     * @param list<string> $claimedEntryIds the identities the claim fold won
     *
     * @return list<DeliveryEnvelope>
     *
     * @throws InvalidArgumentException when the multi-read's answer
     *                                  does not answer one element per key asked, or a record
     *                                  refuses to decode
     */
    public function answeredDeliveries(array $claimedEntryIds): array
    {
        if ($claimedEntryIds === []) {
            return [];
        }

        $entryKeys = [];

        foreach ($claimedEntryIds as $entryId) {
            $entryKeys[] = $this->redisDeliveryKeys->entryKey($entryId);
        }

        $records = $this->respReplyMultiReadShape->bulkOrNullList(
            $this->respConnection->command('MGET', ...$entryKeys),
            'MGET',
        );

        if (\count($records) !== \count($claimedEntryIds)) {
            throw new InvalidArgumentException(
                'The MGET reply is refused: ' . \count($claimedEntryIds)
                . ' entry records were asked for, the reply answered '
                . \count($records) . ' elements.',
            );
        }

        $deliveries = [];

        foreach ($claimedEntryIds as $index => $entryId) {
            $record = $records[$index];

            if ($record === null) {
                $this->respReplyShape->integer(
                    $this->respConnection->command(
                        'ZREM',
                        $this->redisDeliveryKeys->pendingKey(),
                        $entryId,
                    ),
                    'ZREM',
                );

                continue;
            }

            $deliveries[] = $this->decodeRecord($entryId, $record);
        }

        return $deliveries;
    }

    /**
     * Decodes one claimed entry's record, refusing the corrupt or
     * hand-edited record by the entry's own identity.
     */
    private function decodeRecord(string $entryId, string $record): DeliveryEnvelope
    {
        try {
            return $this->deliveryEnvelopeDecoder->decode($record);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new InvalidArgumentException(
                "The delivery entry record '{$entryId}' is refused: "
                . $invalidArgumentException->getMessage(),
                0,
                $invalidArgumentException,
            );
        }
    }
}
