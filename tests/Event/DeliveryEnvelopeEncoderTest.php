<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\DeliveryEnvelopeDecoder;
use Zef\Framework\Event\DeliveryEnvelopeEncoder;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\ListenerIdentity;

/**
 * Pins the durable spelling's writing half: the round trip over
 * every kind the vocabulary carries (the float that keeps its
 * zero fraction, the ready-at at the integer ceiling, the unicode
 * string), and the dead-letter record's own keys -- the reading
 * half's refusals are {@see DeliveryEnvelopeDecoderTest}'s own
 * pins.
 *
 * @internal
 */
#[CoversClass(DeliveryEnvelopeEncoder::class)]
final class DeliveryEnvelopeEncoderTest extends TestCase
{
    public function testTheRoundTripAnswersTheSameEnvelope(): void
    {
        $deliveryEnvelopeEncoder = new DeliveryEnvelopeEncoder();
        $deliveryEnvelope = new DeliveryEnvelope(
            new DeliveryEntryId('0123456789abcdef0123456789abcdef'),
            new EventName('order.paid'),
            [
                'amount' => 12,
                'note' => 'paid in full',
                'flag' => true,
                'ratio' => 0.25,
                'tags' => ['a', 'b'],
                'extra' => null,
            ],
            new ListenerIdentity('app.orders.audit'),
            3,
            1700,
        );

        $decoded = new DeliveryEnvelopeDecoder()->decode($deliveryEnvelopeEncoder->encode($deliveryEnvelope));

        self::assertSame($deliveryEnvelope->entryId()->spelling(), $decoded->entryId()->spelling());
        self::assertSame($deliveryEnvelope->eventName()->spelling(), $decoded->eventName()->spelling());
        self::assertSame($deliveryEnvelope->values(), $decoded->values());
        self::assertSame(
            $deliveryEnvelope->listenerIdentity()->spelling(),
            $decoded->listenerIdentity()->spelling(),
        );
        self::assertSame(3, $decoded->attempt());
        self::assertSame(1700, $decoded->readyAt());
    }

    public function testAFloatWithAWholeValueKeepsItsFloatKindAcrossTheTrip(): void
    {
        $deliveryEnvelopeEncoder = new DeliveryEnvelopeEncoder();
        $envelope = $this->envelope(['ratio' => 1.0]);

        $decoded = new DeliveryEnvelopeDecoder()->decode($deliveryEnvelopeEncoder->encode($envelope));

        self::assertIsFloat($decoded->values()['ratio']);
    }

    public function testAReadyAtAtTheIntegerCeilingSurvivesTheTrip(): void
    {
        $deliveryEnvelopeEncoder = new DeliveryEnvelopeEncoder();
        $envelope = $this->envelope(['amount' => 1], 1, \PHP_INT_MAX);

        $decoded = new DeliveryEnvelopeDecoder()->decode($deliveryEnvelopeEncoder->encode($envelope));

        self::assertSame(\PHP_INT_MAX, $decoded->readyAt());
    }

    public function testAUnicodeStringSurvivesTheTrip(): void
    {
        $deliveryEnvelopeEncoder = new DeliveryEnvelopeEncoder();
        $envelope = $this->envelope(['note' => 'Zalążek — zapłacone']);

        $decoded = new DeliveryEnvelopeDecoder()->decode($deliveryEnvelopeEncoder->encode($envelope));

        self::assertSame('Zalążek — zapłacone', $decoded->values()['note']);
    }

    public function testAnEmptyListAndAMapSurviveTheTrip(): void
    {
        $deliveryEnvelopeEncoder = new DeliveryEnvelopeEncoder();
        $envelope = $this->envelope(['empty' => [], 'user' => ['name' => 'Ada']]);

        $decoded = new DeliveryEnvelopeDecoder()->decode($deliveryEnvelopeEncoder->encode($envelope));

        self::assertSame(['empty' => [], 'user' => ['name' => 'Ada']], $decoded->values());
    }

    public function testTheDeadLetterRecordKeepsTheFloatsZeroFraction(): void
    {
        $deliveryEnvelopeEncoder = new DeliveryEnvelopeEncoder();
        $envelope = $this->envelope(['ratio' => 1.0]);

        $record = json_decode(
            $deliveryEnvelopeEncoder->encodeDeadLetter($envelope, 'RuntimeException', 'failed', 99),
            true,
        );

        self::assertIsArray($record);
        $values = $record['values'];
        self::assertIsArray($values);
        self::assertSame(1.0, $values['ratio']);
    }

    public function testTheDeadLetterRecordCarriesTheStoryKeys(): void
    {
        $deliveryEnvelopeEncoder = new DeliveryEnvelopeEncoder();
        $envelope = $this->envelope(['amount' => 12], 2, 30);

        $record = json_decode(
            $deliveryEnvelopeEncoder->encodeDeadLetter($envelope, 'RuntimeException', 'the listener failed', 99),
            true,
        );

        self::assertIsArray($record);
        self::assertSame('0123456789abcdef0123456789abcdef', $record['entry_id']);
        self::assertSame('order.paid', $record['event_name']);
        self::assertSame(['amount' => 12], $record['values']);
        self::assertSame('app.orders.audit', $record['listener_identity']);
        self::assertSame(2, $record['attempt']);
        self::assertSame(30, $record['ready_at']);
        self::assertSame('RuntimeException', $record['failure_class']);
        self::assertSame('the listener failed', $record['failure_message']);
        self::assertSame(99, $record['dead_at']);
    }

    /**
     * @param array<string, null|array<mixed>|bool|float|int|string> $values
     */
    private function envelope(array $values, int $attempt = 1, int $readyAt = 0): DeliveryEnvelope
    {
        return new DeliveryEnvelope(
            new DeliveryEntryId('0123456789abcdef0123456789abcdef'),
            new EventName('order.paid'),
            $values,
            new ListenerIdentity('app.orders.audit'),
            $attempt,
            $readyAt,
        );
    }
}
