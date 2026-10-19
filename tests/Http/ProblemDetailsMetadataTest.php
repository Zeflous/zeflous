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

    /** @return iterable<non-empty-string, array{int, string, string}> */
    public static function provideOwnedStatusesAnswerTheirStableTypeAndTitleCases(): iterable
    {
        yield 'validation answers 400' => [400, 'validation-error', 'Bad Request'];

        yield 'authentication answers 401' => [401, 'authentication-required', 'Authentication Required'];

        yield 'forbidden answers 403' => [403, 'forbidden', 'Forbidden'];

        yield 'not-found answers 404' => [404, 'not-found', 'Resource Not Found'];

        yield 'method-not-allowed answers 405' => [405, 'method-not-allowed', 'Method Not Allowed'];

        yield 'conflict answers 409' => [409, 'conflict', 'Conflict'];

        yield 'content-too-large answers 413' => [413, 'content-too-large', 'Content Too Large'];

        yield 'rate-limit-exceeded answers 429' => [429, 'rate-limit-exceeded', 'Too Many Requests'];
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
