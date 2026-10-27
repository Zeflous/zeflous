<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\DeliveryEnvelopeDecoder;
use Zef\Framework\Event\DeliveryRecordShape;
use Zef\Framework\Event\DeliveryRecordValues;

/**
 * Pins the durable spelling's reading half: every decode refusal
 * -- invalid JSON, a non-object record, unknown keys, missing
 * keys, wrongly-typed fields, the bounds, and the non-string
 * field name the JSON grammar itself cannot carry. A record that
 * decodes on a guess is a delivery that was never admitted.
 *
 * @internal
 */
#[CoversClass(DeliveryEnvelopeDecoder::class)]
#[CoversClass(DeliveryRecordShape::class)]
#[CoversClass(DeliveryRecordValues::class)]
final class DeliveryEnvelopeDecoderTest extends TestCase
{
    public function testARecordThatIsNotValidJsonIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();

        try {
            $deliveryEnvelopeDecoder->decode('{not json');
            self::fail('The invalid JSON was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The durable delivery record is not valid JSON: Syntax error.',
                $invalidArgumentException->getMessage(),
            );
            self::assertSame(0, $invalidArgumentException->getCode());
        }
    }

    public function testAWellFormedRecordDecodesWithAllSixParts(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();

        $envelope = $deliveryEnvelopeDecoder->decode(
            $this->recordJson(['values' => ['amount' => 12, 'memo' => 'paid']]),
        );

        self::assertSame('0123456789abcdef0123456789abcdef', $envelope->entryId()->spelling());
        self::assertSame('order.paid', $envelope->eventName()->spelling());
        self::assertSame(['amount' => 12, 'memo' => 'paid'], $envelope->values());
        self::assertSame('app.orders.audit', $envelope->listenerIdentity()->spelling());
        self::assertSame(1, $envelope->attempt());
        self::assertSame(0, $envelope->readyAt());
    }

    public function testARecordNestedToTheExactDepthLimitDecodes(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();
        $record = $this->record([]);
        $record['values'] = $this->nested(510);

        $envelope = $deliveryEnvelopeDecoder->decode((string) json_encode($record));

        self::assertSame(0, $envelope->readyAt());
    }

    public function testARecordNestedPastTheDepthLimitIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();
        $record = $this->record([]);
        $record['values'] = $this->nested(511);

        try {
            $deliveryEnvelopeDecoder->decode((string) json_encode($record));
            self::fail('The over-deep record was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertStringStartsWith(
                'The durable delivery record is not valid JSON: Maximum stack'
                . ' depth exceeded',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordThatIsNotAnObjectIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();

        try {
            $deliveryEnvelopeDecoder->decode('42');
            self::fail('The scalar record was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The durable delivery record must be a JSON object, got int.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordWithAnUnknownKeyIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();

        try {
            $deliveryEnvelopeDecoder->decode($this->recordJson(['extra' => 1]));
            self::fail('The unknown key was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The durable delivery record carries an unknown key 'extra'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordThatMissesAKeyIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();
        $record = $this->record([]);
        unset($record['attempt']);

        try {
            $deliveryEnvelopeDecoder->decode((string) json_encode($record));
            self::fail('The missing key was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The durable delivery record misses the 'attempt' key.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordWithANonStringEntryIdIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();

        try {
            $deliveryEnvelopeDecoder->decode($this->recordJson(['entry_id' => 1]));
            self::fail('The non-string entry id was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The durable delivery record's entry id must be a string, got int.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordWithANonStringEventNameIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();

        try {
            $deliveryEnvelopeDecoder->decode($this->recordJson(['event_name' => 1]));
            self::fail('The non-string event name was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The durable delivery record's event name must be a string, got int.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordWithANonObjectValuesMapIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();

        try {
            $deliveryEnvelopeDecoder->decode($this->recordJson(['values' => 'none']));
            self::fail('The non-object values map was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The durable delivery record's values must be a JSON object, got string.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordWithANonStringListenerIdentityIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();

        try {
            $deliveryEnvelopeDecoder->decode($this->recordJson(['listener_identity' => 1]));
            self::fail('The non-string listener identity was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The durable delivery record's listener identity must be a string, got int.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordWithANonIntegerAttemptIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();

        try {
            $deliveryEnvelopeDecoder->decode($this->recordJson(['attempt' => 1.5]));
            self::fail('The float attempt was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The durable delivery record's attempt must be an integer, got float.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordWithAnAttemptBelowOneIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();

        try {
            $deliveryEnvelopeDecoder->decode($this->recordJson(['attempt' => 0]));
            self::fail('The zero attempt was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The durable delivery record's attempt must stand at one or later, got 0.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordWithANonIntegerReadyAtIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();

        try {
            $deliveryEnvelopeDecoder->decode($this->recordJson(['ready_at' => 'soon']));
            self::fail('The string ready-at was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The durable delivery record's ready-at must be an integer, got string.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordWithANegativeReadyAtIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();

        try {
            $deliveryEnvelopeDecoder->decode($this->recordJson(['ready_at' => -1]));
            self::fail('The negative ready-at was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The durable delivery record's ready-at cannot be negative, got -1.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordWhoseValuesCarryANumericFieldKeyIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();
        $record = $this->record([]);
        $record['values'] = [0 => 'zero'];

        try {
            $deliveryEnvelopeDecoder->decode((string) json_encode($record));
            self::fail('The numeric field key was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The durable delivery record's values must carry string field"
                . ' names, got the key 0.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testARecordWhoseEntryIdMisspellsTheHexGrammarIsRefused(): void
    {
        $deliveryEnvelopeDecoder = new DeliveryEnvelopeDecoder();

        try {
            $deliveryEnvelopeDecoder->decode($this->recordJson(['entry_id' => 'not-hex']));
            self::fail('The misspelled entry id was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A delivery entry id must be exactly 32 lowercase hexadecimal'
                . " characters, got 'not-hex'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    /**
     * Builds the record's JSON spelling with the given overrides applied.
     *
     * @param array<string, mixed> $overrides
     */
    private function recordJson(array $overrides): string
    {
        return (string) json_encode($this->record($overrides));
    }

    /**
     * @param positive-int $depth
     *
     * @return array<string, mixed> the map nested to the given depth
     */
    private function nested(int $depth): array
    {
        $nested = ['k' => 'leaf'];

        for ($level = 1; $level < $depth; ++$level) {
            $nested = ['k' => $nested];
        }

        return $nested;
    }

    /**
     * Builds the record's map with the given overrides applied.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function record(array $overrides): array
    {
        return [
            'entry_id' => '0123456789abcdef0123456789abcdef',
            'event_name' => 'order.paid',
            'values' => ['amount' => 12],
            'listener_identity' => 'app.orders.audit',
            'attempt' => 1,
            'ready_at' => 0,
            ...$overrides,
        ];
    }
}
