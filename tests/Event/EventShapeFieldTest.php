<?php

declare(strict_types=1);

namespace Zef\Test\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Event\EventShapeField;
use Zef\Framework\Event\PayloadFieldType;

/**
 * Pins the field-spelling grammar the shape map accepts: one kind
 * word, optionally `?`-prefixed for a nullable field -- and the
 * refusal that names the whole accepted vocabulary when a producer
 * invents a spelling the parser never heard of.
 *
 * @internal
 */
#[CoversClass(EventShapeField::class)]
final class EventShapeFieldTest extends TestCase
{
    public function testABareSpellingDemandsItsOwnKind(): void
    {
        $eventShapeField = new EventShapeField('int');

        self::assertSame(PayloadFieldType::Integer, $eventShapeField->type());
        self::assertFalse($eventShapeField->isNullable());
    }

    public function testTheNullablePrefixLetsNullStandIn(): void
    {
        $eventShapeField = new EventShapeField('?string');

        self::assertSame(PayloadFieldType::String, $eventShapeField->type());
        self::assertTrue($eventShapeField->isNullable());
    }

    public function testEveryKindHasABareAndANullableSpelling(): void
    {
        foreach (['string', 'int', 'float', 'bool', 'list'] as $spelling) {
            $bare = new EventShapeField($spelling);
            $nullable = new EventShapeField('?' . $spelling);

            self::assertSame($bare->type(), $nullable->type());
            self::assertFalse($bare->isNullable());
            self::assertTrue($nullable->isNullable());
        }
    }

    public function testAnUnknownSpellingIsRefusedWithTheVocabularyNamed(): void
    {
        try {
            new EventShapeField('datetime');
            self::fail('The unknown spelling was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event shape field must spell one of the payload kinds'
                . ' (string, int, float, bool, list), optionally prefixed with'
                . " '?' for a nullable field, got 'datetime'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAnUnknownNullableSpellingIsRefusedToo(): void
    {
        try {
            new EventShapeField('?datetime');
            self::fail('The unknown nullable spelling was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event shape field must spell one of the payload kinds'
                . ' (string, int, float, bool, list), optionally prefixed with'
                . " '?' for a nullable field, got '?datetime'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testABareNullablePrefixAloneIsRefused(): void
    {
        try {
            new EventShapeField('?');
            self::fail('The bare nullable prefix was not refused.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'An event shape field must spell one of the payload kinds'
                . ' (string, int, float, bool, list), optionally prefixed with'
                . " '?' for a nullable field, got '?'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }

    public function testAcceptsAnswersTheNullQuestionForTheField(): void
    {
        self::assertTrue(new EventShapeField('?int')->accepts(null));
        self::assertFalse(new EventShapeField('int')->accepts(null));
        self::assertTrue(new EventShapeField('int')->accepts(3));
        self::assertFalse(new EventShapeField('int')->accepts('3'));
    }
}
