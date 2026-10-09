<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Tooling\ReviewThreadReport;
use Zef\Framework\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(ReviewThreadReport::class)]
final class ReviewThreadReportTest extends TestCase
{
    public function testParsesThreadsAndSelectsOnlyFinishedConversations(): void
    {
        $reviewThreadReport = ReviewThreadReport::fromGraphQlPayload($this->payload([
            ['id' => 'PRRT_a', 'isResolved' => false, 'isOutdated' => true, 'path' => 'src/A.php'],
            ['id' => 'PRRT_b', 'isResolved' => false, 'isOutdated' => false, 'path' => 'src/B.php'],
            ['id' => 'PRRT_c', 'isResolved' => true, 'isOutdated' => true, 'path' => 'src/C.php'],
        ]));

        self::assertSame(3, $reviewThreadReport->count());
        self::assertSame(['PRRT_a'], $reviewThreadReport->resolvableIds());
    }

    public function testEmptyNodeListYieldsNoResolvableIds(): void
    {
        $reviewThreadReport = ReviewThreadReport::fromGraphQlPayload($this->payload([]));

        self::assertSame(0, $reviewThreadReport->count());
        self::assertSame([], $reviewThreadReport->resolvableIds());
    }

    public function testMissingBooleanFlagsDefaultToFalse(): void
    {
        $reviewThreadReport = ReviewThreadReport::fromGraphQlPayload($this->payload([
            ['id' => 'PRRT_d'],
        ]));

        self::assertSame(1, $reviewThreadReport->count());
        self::assertSame([], $reviewThreadReport->resolvableIds());
    }

    public function testNonBooleanFlagsAreTreatedAsFalse(): void
    {
        $reviewThreadReport = ReviewThreadReport::fromGraphQlPayload($this->payload([
            ['id' => 'PRRT_e', 'isResolved' => 'yes', 'isOutdated' => 1, 'path' => 42],
        ]));

        self::assertSame(1, $reviewThreadReport->count());
        self::assertSame([], $reviewThreadReport->resolvableIds());
    }

    public function testThreadWithoutUsableIdIsSkipped(): void
    {
        $reviewThreadReport = ReviewThreadReport::fromGraphQlPayload($this->payload([
            ['isResolved' => false, 'isOutdated' => true],
            ['id' => '', 'isResolved' => false, 'isOutdated' => true],
            ['id' => 123, 'isResolved' => false, 'isOutdated' => true],
            ['id' => 'PRRT_ok', 'isResolved' => false, 'isOutdated' => true],
        ]));

        self::assertSame(1, $reviewThreadReport->count());
        self::assertSame(['PRRT_ok'], $reviewThreadReport->resolvableIds());
    }

    public function testNonArrayNodeIsSkipped(): void
    {
        $reviewThreadReport = ReviewThreadReport::fromGraphQlPayload($this->payload([
            'not-an-object',
            ['id' => 'PRRT_ok', 'isResolved' => false, 'isOutdated' => true],
        ]));

        self::assertSame(1, $reviewThreadReport->count());
        self::assertSame(['PRRT_ok'], $reviewThreadReport->resolvableIds());
    }

    public function testInvalidJsonIsRejected(): void
    {
        try {
            ReviewThreadReport::fromGraphQlPayload('{not json');
            self::fail('Expected a ToolingException for invalid JSON.');
        } catch (ToolingException $toolingException) {
            self::assertStringContainsString('not valid JSON', $toolingException->getMessage());
        }
    }

    public function testMissingNodesShapeIsRejected(): void
    {
        try {
            ReviewThreadReport::fromGraphQlPayload((string) json_encode(['data' => ['repository' => []]]));
            self::fail('Expected a ToolingException for a missing nodes shape.');
        } catch (ToolingException $toolingException) {
            self::assertStringContainsString('missing reviewThreads.nodes', $toolingException->getMessage());
        }
    }

    /**
     * @param list<mixed> $nodes
     */
    private function payload(array $nodes): string
    {
        return (string) json_encode([
            'data' => [
                'repository' => [
                    'pullRequest' => [
                        'reviewThreads' => [
                            'totalCount' => \count($nodes),
                            'nodes' => $nodes,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
