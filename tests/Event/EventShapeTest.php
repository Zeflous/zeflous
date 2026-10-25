<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\EventPayload;
use Zef\Framework\Event\EventShape;
use Zef\Framework\Event\PayloadFieldType;

/**
 * Pins the payload contract's three fail-closed directions -- an
 * unknown field, a missing demanded field, a field answering the
 * wrong kind -- and the one direction that stays open: an explicit
 * null standing in for a `?`-spelled field. Every refusal names
 * the field, the demanded kind and the found kind, so the
 * mis-shaped producer reads its own mistake at the dispatch call
 * site instead of inside some far-away listener.
 *
 * @internal
 */
#[CoversClass(EventShape::class)]
#[CoversClass(EventPayload::class)]
final class EventShapeTest extends TestCase
{
    public function testAValidPayloadIsWrappedAsThePayload(): void
    {
        $eventShape = new EventShape(['userId' => 'int', 'email' => '?string']);

        $payload = $eventShape->validate(['userId' => 7, 'email' => null]);

        self::assertSame(7, $payload->value('userId'));
        self::assertNull($payload->value('email'));
    }

    public function testEveryKindSurvivesTheRoundTrip(): void
    {
        $eventShape = new EventShape([
            'name' => 'string',
            'count' => 'int',
            'ratio' => 'float',
            'flag' => 'bool',
            'items' => 'list',
        ]);

        $payload = $eventShape->validate([
            'name' => 'zef',
            'count' => 2,
            'ratio' => 0.5,
            'flag' => true,
            'items' => [1, 2],
        ]);

        self::assertSame('zef', $payload->value('name'));
        self::assertSame(2, $payload->value('count'));
        self::assertSame(0.5, $payload->value('ratio'));
        self::assertTrue($payload->value('flag'));
        self::assertSame([1, 2], $payload->value('items'));
    }

    public function testTheEmptyShapeIsRefused(): void
    {
        try {
            new EventShape([]);
            self::fail('The empty shape was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event shape must demand at least one field; the empty'
                . ' shape would validate any payload and catch nothing.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnUnknownFieldIsRefusedByName(): void
    {
        $eventShape = new EventShape(['userId' => 'int']);

        try {
            $eventShape->validate(['userId' => 7, 'email' => 'a@b.c']);
            self::fail('The unknown payload field was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The payload carries a field the event shape never demanded: 'email'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAMissingFieldIsRefusedWithItsKindAndNullability(): void
    {
        $eventShape = new EventShape(['userId' => 'int', 'email' => '?string']);

        try {
            $eventShape->validate(['userId' => 7]);
            self::fail('The missing nullable field was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The payload misses the field the event shape demands: 'email' (string, nullable).",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAMissingNonNullableFieldNamesItsOwnArm(): void
    {
        $eventShape = new EventShape(['userId' => 'int']);

        try {
            $eventShape->validate([]);
            self::fail('The missing non-nullable field was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The payload misses the field the event shape demands: 'userId' (int).",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAWrongKindIsRefusedWithBothKindsNamed(): void
    {
        $eventShape = new EventShape(['userId' => 'int']);

        try {
            $eventShape->validate(['userId' => '7']);
            self::fail('The wrong-kind field was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The payload field 'userId' must answer int, got string.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANullOnANonNullableFieldIsRefusedWithBothArmsNamed(): void
    {
        $eventShape = new EventShape(['userId' => 'int']);

        try {
            $eventShape->validate(['userId' => null]);
            self::fail('The null on a non-nullable field was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The payload field 'userId' must answer int, got null.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testANumericFieldKeyIsRefused(): void
    {
        // PHP casts the numeric string key '0' to the integer 0 --
        // a field name the shape never accepts.
        try {
            new EventShape(['0' => 'int']);
            self::fail('The numeric field key was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event shape field must be named with a non-empty string key.',
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAWrongKindOnANullableFieldNamesBothArms(): void
    {
        $eventShape = new EventShape(['email' => '?string']);

        try {
            $eventShape->validate(['email' => 5]);
            self::fail('The wrong kind on the nullable field was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The payload field 'email' must answer string or null, got int.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testTheFieldsAreCarriedInDeclarationOrder(): void
    {
        $eventShape = new EventShape(['second' => 'int', 'first' => 'int']);

        self::assertSame(['second', 'first'], array_keys($eventShape->fields()));
    }

    public function testTheFieldsCarryTheirParsedShape(): void
    {
        $eventShape = new EventShape(['email' => '?string']);

        $field = $eventShape->fields()['email'];

        self::assertSame(PayloadFieldType::String, $field->type());
        self::assertTrue($field->isNullable());
    }

    public function testThePayloadAnswersItsOwnFieldList(): void
    {
        $payload = new EventShape(['userId' => 'int'])->validate(['userId' => 7]);

        self::assertSame(['userId'], $payload->fields());
    }

    public function testThePayloadRefusesAFieldItDoesNotCarry(): void
    {
        $payload = new EventShape(['userId' => 'int'])->validate(['userId' => 7]);

        try {
            $payload->value('email');
            self::fail('The absent payload field was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                "The event payload does not carry a 'email' field.",
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
