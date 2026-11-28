<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use JsonException;
use Zef\Framework\Event\DeliveryRecordValues;

/**
 * The PDO-backed outbox store's payload fold: one row's payload
 * decoded fail-closed, naming the row -- the JSON spelling the
 * append wrote, read back through the Event lane's own values
 * walk, so a corrupt or hand-edited payload is never re-answered
 * as an event the host never appended (the fail-closed doctrine
 * every answer over the port family carries). The question is the
 * decode's alone: the cells that carry the payload live in the
 * records fold ({@see PdoOutboxRecords}), the values walk itself
 * is the Event lane's own ({@see DeliveryRecordValues}).
 */
final readonly class PdoOutboxPayload
{
    private DeliveryRecordValues $deliveryRecordValues;

    public function __construct()
    {
        $this->deliveryRecordValues = new DeliveryRecordValues();
    }

    /**
     * Decodes one row's payload into the values the message
     * carries.
     *
     * @return array<string, null|array<mixed>|bool|float|int|string>
     *
     * @throws InvalidArgumentException when the payload refuses to
     *                                  decode, or decodes to a shape the values contract does
     *                                  not spell
     */
    public function decode(string $messageId, string $payload): array
    {
        try {
            /** @var mixed $decoded the decoded payload */
            $decoded = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException(
                "The outbox row '{$messageId}' carries a payload that refuses"
                . ' to decode: ' . $jsonException->getMessage(),
                0,
                $jsonException,
            );
        }

        try {
            return $this->deliveryRecordValues->narrow($decoded);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new InvalidArgumentException(
                "The outbox row '{$messageId}' carries a payload that refuses"
                . ' to decode: ' . $invalidArgumentException->getMessage(),
                0,
                $invalidArgumentException,
            );
        }
    }
}
