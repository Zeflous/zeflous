<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ProblemDetailsMetadata;

/**
 * Pins the complete RFC 9457 status vocabulary: every owned match arm and
 * the default fallback are asserted for both the type URI and the title.
 *
 * @internal
 */
#[CoversClass(ProblemDetailsMetadata::class)]
final class ProblemDetailsMetadataTest extends TestCase
{
    #[DataProvider('provideOwnedStatusesAnswerTheirStableTypeAndTitleCases')]
    public function testOwnedStatusesAnswerTheirStableTypeAndTitle(int $status, string $slug, string $title): void
    {
        self::assertSame('https://zef.io/errors/' . $slug, ProblemDetailsMetadata::typeFor($status));
        self::assertSame($title, ProblemDetailsMetadata::titleFor($status));
    }

    /** @return list<array{int, string, string}> */
    public static function provideOwnedStatusesAnswerTheirStableTypeAndTitleCases(): iterable
    {
        return [
            [400, 'validation-error', 'Bad Request'],
            [401, 'authentication-required', 'Authentication Required'],
            [403, 'forbidden', 'Forbidden'],
            [404, 'not-found', 'Resource Not Found'],
            [405, 'method-not-allowed', 'Method Not Allowed'],
            [409, 'conflict', 'Conflict'],
            [413, 'content-too-large', 'Content Too Large'],
            [429, 'rate-limit-exceeded', 'Too Many Requests'],
        ];
    }

    public function testUnownedStatusesFallBackToTheInternalErrorTypeAndTitle(): void
    {
        self::assertSame('https://zef.io/errors/internal-error', ProblemDetailsMetadata::typeFor(500));
        self::assertSame('Internal Server Error', ProblemDetailsMetadata::titleFor(500));
        self::assertSame('https://zef.io/errors/internal-error', ProblemDetailsMetadata::typeFor(503));
        self::assertSame('Internal Server Error', ProblemDetailsMetadata::titleFor(503));
        self::assertSame('https://zef.io/errors/internal-error', ProblemDetailsMetadata::typeFor(418));
        self::assertSame('Internal Server Error', ProblemDetailsMetadata::titleFor(418));
    }
}
