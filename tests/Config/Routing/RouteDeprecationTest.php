<?php

declare(strict_types=1);

namespace Zef\Test\Config\Routing;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Routing\RouteDeprecation;
use Zef\Framework\Config\Routing\RoutePath;

/**
 * @internal
 */
#[CoversClass(RouteDeprecation::class)]
final class RouteDeprecationTest extends TestCase
{
    public function testACalendarDateReadsAsThatDaysMidnightUtc(): void
    {
        $deprecation = RouteDeprecation::canonical(
            ['date' => '2024-06-30'],
            RoutePath::parse('/legacy'),
        );

        self::assertSame(
            new DateTimeImmutable('2024-06-30T00:00:00+00:00')->getTimestamp(),
            $deprecation['date'],
        );
        self::assertNull($deprecation['sunset']);
        self::assertNull($deprecation['link']);
    }

    public function testAFullInstantWithTheUtcDesignatorKeepsItsInstant(): void
    {
        $deprecation = RouteDeprecation::canonical(
            ['date' => '2023-06-30T23:59:59Z'],
            RoutePath::parse('/legacy'),
        );

        self::assertSame(1688169599, $deprecation['date']);
    }

    public function testAPositiveOffsetKeepsItsOwnFrame(): void
    {
        // 14:00 at +02:00 is the same instant as 12:00 UTC.
        $deprecation = RouteDeprecation::canonical(
            ['date' => '2024-06-30T14:00:00+02:00'],
            RoutePath::parse('/legacy'),
        );

        self::assertSame(
            new DateTimeImmutable('2024-06-30T12:00:00+00:00')->getTimestamp(),
            $deprecation['date'],
        );
    }

    public function testANegativeOffsetKeepsItsOwnFrame(): void
    {
        // 09:30 at -05:00 is the same instant as 14:30 UTC.
        $deprecation = RouteDeprecation::canonical(
            ['date' => '2024-06-30T09:30:00-05:00'],
            RoutePath::parse('/legacy'),
        );

        self::assertSame(
            new DateTimeImmutable('2024-06-30T14:30:00+00:00')->getTimestamp(),
            $deprecation['date'],
        );
    }

    public function testASunsetAfterTheDeprecationDateIsKept(): void
    {
        $deprecation = RouteDeprecation::canonical(
            ['date' => '2024-06-30', 'sunset' => '2025-06-30T12:00:00Z'],
            RoutePath::parse('/legacy'),
        );

        self::assertSame(
            new DateTimeImmutable('2025-06-30T12:00:00+00:00')->getTimestamp(),
            $deprecation['sunset'],
        );
    }

    public function testALinkIsKeptVerbatim(): void
    {
        $deprecation = RouteDeprecation::canonical(
            ['date' => '2024-06-30', 'link' => 'https://example.com/deprecations/legacy'],
            RoutePath::parse('/legacy'),
        );

        self::assertSame('https://example.com/deprecations/legacy', $deprecation['link']);
    }

    public function testARelativeLinkReferenceIsKept(): void
    {
        // RFC 8288 allows URI-references as link targets; the declaration
        // does not demand an absolute URI.
        $deprecation = RouteDeprecation::canonical(
            ['date' => '2024-06-30', 'link' => '/deprecations/legacy'],
            RoutePath::parse('/legacy'),
        );

        self::assertSame('/deprecations/legacy', $deprecation['link']);
    }

    public function testADateWithoutAZoneDesignatorIsRefused(): void
    {
        $this->assertRefused(
            ['date' => '2024-06-30T14:00:00'],
            'The route "/legacy" declares a deprecation "date" of "2024-06-30T14:00:00" that is neither a calendar date'
            . " (2024-06-30, read as that day's midnight UTC) nor a full date-time with an explicit"
            . ' zone designator (2024-06-30T12:00:00Z or 2024-06-30T14:00:00+02:00).',
        );
    }

    public function testASeparatorSlashIsRefused(): void
    {
        $this->assertRefused(
            ['date' => '2024/06/30'],
            'The route "/legacy" declares a deprecation "date" of "2024/06/30" that is neither a calendar date'
            . " (2024-06-30, read as that day's midnight UTC) nor a full date-time with an explicit"
            . ' zone designator (2024-06-30T12:00:00Z or 2024-06-30T14:00:00+02:00).',
        );
    }

    public function testARolledOverDateIsRefusedRatherThanCarried(): void
    {
        // 2024 is a leap year, so February 30th does not exist; the
        // engine would roll it over to March 1st, which is not the date
        // the declaration named.
        $this->assertRefused(
            ['date' => '2024-02-30'],
            'The route "/legacy" declares a deprecation "date" of "2024-02-30" that is neither a calendar date'
            . " (2024-06-30, read as that day's midnight UTC) nor a full date-time with an explicit"
            . ' zone designator (2024-06-30T12:00:00Z or 2024-06-30T14:00:00+02:00).',
        );
    }

    public function testASecondOfSixtyIsRefusedRatherThanCarried(): void
    {
        $this->assertRefused(
            ['date' => '2024-06-30T23:59:60Z'],
            'The route "/legacy" declares a deprecation "date" of "2024-06-30T23:59:60Z" that is neither a calendar'
            . " date (2024-06-30, read as that day's midnight UTC) nor a full date-time with an explicit"
            . ' zone designator (2024-06-30T12:00:00Z or 2024-06-30T14:00:00+02:00).',
        );
    }

    public function testASunsetBeforeTheDeprecationDateIsRefused(): void
    {
        $this->assertRefused(
            ['date' => '2024-06-30', 'sunset' => '2024-01-01'],
            'The route "/legacy" declares a sunset at or before its deprecation date.'
            . ' A resource that retires when it is deprecated is a retirement, not a deprecation.',
        );
    }

    public function testASunsetAtTheDeprecationInstantIsRefused(): void
    {
        $this->assertRefused(
            ['date' => '2024-06-30T12:00:00Z', 'sunset' => '2024-06-30T12:00:00Z'],
            'The route "/legacy" declares a sunset at or before its deprecation date.'
            . ' A resource that retires when it is deprecated is a retirement, not a deprecation.',
        );
    }

    public function testALinkCarryingWhitespaceIsRefused(): void
    {
        $this->assertRefused(
            ['date' => '2024-06-30', 'link' => 'https://example.com/a b'],
            'The route "/legacy" declares a deprecation "link" that cannot render as a link target:'
            . ' it carries whitespace, a control byte, or an angle bracket ("https://example.com/a b").',
        );
    }

    public function testALinkCarryingAnAngleBracketIsRefused(): void
    {
        $this->assertRefused(
            ['date' => '2024-06-30', 'link' => 'https://example.com/<legacy>'],
            'The route "/legacy" declares a deprecation "link" that cannot render as a link target:'
            . ' it carries whitespace, a control byte, or an angle bracket ("https://example.com/<legacy>").',
        );
    }

    public function testALinkCarryingAControlByteIsRefused(): void
    {
        $controlByteLink = "https://example.com/\u{0007}legacy";

        $this->assertRefused(
            ['date' => '2024-06-30', 'link' => $controlByteLink],
            'The route "/legacy" declares a deprecation "link" that cannot render as a link target:'
            . ' it carries whitespace, a control byte, or an angle bracket ("' . $controlByteLink . '").',
        );
    }

    public function testTheMemberVocabularyIsTheThreeRfcMembers(): void
    {
        self::assertSame(
            ['date' => true, 'sunset' => true, 'link' => true],
            RouteDeprecation::members(),
        );
    }

    /**
     * @param array{date: non-empty-string, sunset?: non-empty-string, link?: non-empty-string} $declaration
     */
    private function assertRefused(array $declaration, string $message): void
    {
        try {
            RouteDeprecation::canonical($declaration, RoutePath::parse('/legacy'));
            self::fail('The deprecation declaration must be refused.');
        } catch (ConfigException $configException) {
            self::assertSame($message, $configException->getMessage());
        }
    }
}
