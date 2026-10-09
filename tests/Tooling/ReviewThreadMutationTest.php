<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\GraphQlErrors;
use Zef\Framework\Tooling\ReviewThreadMutation;

/**
 * @internal
 */
#[CoversClass(GraphQlErrors::class)]
#[CoversClass(ReviewThreadMutation::class)]
final class ReviewThreadMutationTest extends TestCase
{
    public function testASuccessfulMutationSucceeds(): void
    {
        self::assertTrue(ReviewThreadMutation::succeeded((string) json_encode([
            'data' => ['resolveReviewThread' => ['thread' => ['id' => 'PRRT_1', 'isResolved' => true]]],
        ])));
    }

    public function testNonArrayBodyFails(): void
    {
        self::assertFalse(ReviewThreadMutation::succeeded('not json'));
    }

    public function testErrorsWithASuccessfulDataSectionStillFails(): void
    {
        // The errors array wins over an otherwise-successful data section:
        // a response that carries errors must never count as resolved.
        self::assertFalse(ReviewThreadMutation::succeeded((string) json_encode([
            'errors' => [['message' => 'boom']],
            'data' => ['resolveReviewThread' => ['thread' => ['id' => 'PRRT_1', 'isResolved' => true]]],
        ])));
    }

    public function testAnEmptyErrorsArrayIsNotAnError(): void
    {
        self::assertTrue(ReviewThreadMutation::succeeded((string) json_encode([
            'errors' => [],
            'data' => ['resolveReviewThread' => ['thread' => ['id' => 'PRRT_1', 'isResolved' => true]]],
        ])));
    }

    public function testNonArrayErrorsAreNotAnError(): void
    {
        // A malformed (non-array) errors value is ignored, mirroring the
        // fail-closed narrowing: only a real errors ARRAY is an error.
        self::assertTrue(ReviewThreadMutation::succeeded((string) json_encode([
            'errors' => 'malformed',
            'data' => ['resolveReviewThread' => ['thread' => ['id' => 'PRRT_1', 'isResolved' => true]]],
        ])));
    }

    public function testAThreadNotMarkedResolvedFails(): void
    {
        self::assertFalse(ReviewThreadMutation::succeeded((string) json_encode([
            'data' => ['resolveReviewThread' => ['thread' => ['id' => 'PRRT_1', 'isResolved' => false]]],
        ])));
    }

    public function testAMissingThreadSectionFails(): void
    {
        self::assertFalse(ReviewThreadMutation::succeeded((string) json_encode([
            'data' => ['resolveReviewThread' => null],
        ])));
    }

    public function testAMissingDataSectionFails(): void
    {
        self::assertFalse(ReviewThreadMutation::succeeded('{}'));
    }

    public function testGraphQlErrorsDetectsOnlyRealErrorArrays(): void
    {
        $errors = static fn (mixed $value): array => ['errors' => $value];

        self::assertTrue(GraphQlErrors::present($errors([['message' => 'boom']])));
        self::assertFalse(GraphQlErrors::present($errors([])));
        self::assertFalse(GraphQlErrors::present($errors('malformed')));
        self::assertFalse(GraphQlErrors::present($errors(null)));
        self::assertFalse(GraphQlErrors::present(['data' => []]));
    }
}
