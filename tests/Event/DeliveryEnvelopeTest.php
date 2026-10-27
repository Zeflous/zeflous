<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DeliveryEnvelope;
use Zef\Framework\Event\EventName;
use Zef\Framework\Event\ListenerIdentity;

/**
 * Pins the durable record itself: the construction refusals (the
 * attempt floor, the ready-at floor, the walk's own offense), the
 * accessors, and the retry's fresh identity -- the entry that
 * replaces a spent one is a NEW entry, never a rewrite.
 *
 * @internal
 */
#[CoversClass(DeliveryEnvelope::class)]
final class DeliveryEnvelopeTest extends TestCase
{
    public function testAWellFormedEnvelopeAnswersItsParts(): void
    {
        $envelope = $this->envelope(['amount' => 12]);

        self::assertSame('order.paid', $envelope->eventName()->spelling());
        self::assertSame(['amount' => 12], $envelope->values());
        self::assertSame('app.orders.audit', $envelope->listenerIdentity()->spelling());
        self::assertSame(1, $envelope->attempt());
        self::assertSame(0, $envelope->readyAt());
        self::assertNotSame('', $envelope->entryId()->spelling());
    }

    public function testAnAttemptBelowOneIsRefused(): void
    {
        try {
            $this->envelope(['amount' => 12], 0);
            self::fail('The zero attempt was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A durable delivery stands at an attempt number of at least'
                . ' one, got 0.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANegativeReadyAtIsRefused(): void
    {
        try {
            $this->envelope(['amount' => 12], 1, -1);
            self::fail('The negative ready-at was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "A durable delivery's ready-at moment cannot be negative,"
                . ' got -1.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAPayloadValueThatCannotSurviveTheSpellingIsRefused(): void
    {
        try {
            $this->envelope(['tags' => [(object) []]]);
            self::fail('The unspellable payload was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The durable delivery envelope carries a payload value that'
                . ' cannot survive its serialized spelling: stdClass at'
                . ' tags[0].',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheMaximumAttemptAndReadyAtAreLegal(): void
    {
        $envelope = $this->envelope(['amount' => 12], \PHP_INT_MAX, \PHP_INT_MAX);

        self::assertSame(\PHP_INT_MAX, $envelope->attempt());
        self::assertSame(\PHP_INT_MAX, $envelope->readyAt());
    }

    public function testTheRetryCarriesAFreshIdentityAndTheNextAttempt(): void
    {
        $envelope = $this->envelope(['amount' => 12], 3, 100);
        $retry = $envelope->withNextAttempt(4, 700);

        self::assertSame(4, $retry->attempt());
        self::assertSame(700, $retry->readyAt());
        self::assertSame('order.paid', $retry->eventName()->spelling());
        self::assertSame(['amount' => 12], $retry->values());
        self::assertSame('app.orders.audit', $retry->listenerIdentity()->spelling());
        self::assertNotSame(
            $envelope->entryId()->spelling(),
            $retry->entryId()->spelling(),
        );
    }

    /**
     * @param array<string, null|array<mixed>|bool|float|int|string> $values
     */
    private function envelope(array $values, int $attempt = 1, int $readyAt = 0): DeliveryEnvelope
    {
        return new DeliveryEnvelope(
            DeliveryEntryId::mint(),
            new EventName('order.paid'),
            $values,
            new ListenerIdentity('app.orders.audit'),
            $attempt,
            $readyAt,
        );
    }
}
