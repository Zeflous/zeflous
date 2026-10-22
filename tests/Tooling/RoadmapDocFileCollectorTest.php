<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\RoadmapDocFileCollector;

/**
 * @internal
 */
#[CoversClass(RoadmapDocFileCollector::class)]
final class RoadmapDocFileCollectorTest extends TestCase
{
    private string $root;

    #[Override]
    protected function setUp(): void
    {
        $this->root = (string) tempnam(sys_get_temp_dir(), 'zef-roadmap-');
        unlink($this->root);
        mkdir($this->root);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (['ROADMAP-HISTORY.md', 'ROADMAP-STATUS.md', 'CONTRIBUTING.md', 'README.md'] as $name) {
            if (!is_file($this->root . '/' . $name)) {
                continue;
            }

            unlink($this->root . '/' . $name);
        }

        rmdir($this->root);
    }

    public function testCollectsOnlyTheRoadmapDocuments(): void
    {
        file_put_contents($this->root . '/ROADMAP-HISTORY.md', "# History\n");
        file_put_contents($this->root . '/ROADMAP-STATUS.md', "# Status\n");
        file_put_contents($this->root . '/CONTRIBUTING.md', "# Contributing\n");
        // A Markdown file outside the fixed corpus must never be collected.
        file_put_contents($this->root . '/README.md', "# Readme\n");

        $documents = new RoadmapDocFileCollector($this->root)->collect();

        self::assertSame(['ROADMAP-HISTORY.md', 'ROADMAP-STATUS.md', 'CONTRIBUTING.md'], array_keys($documents));
        self::assertSame("# History\n", $documents['ROADMAP-HISTORY.md']);
        self::assertArrayNotHasKey('README.md', $documents);
    }

    public function testSkipsAMissingDocumentButKeepsTheFollowingOnes(): void
    {
        // Only the LAST document in the corpus exists. A `continue` must still
        // reach it; a `break` would drop it, so this orders the case that
        // distinguishes "skip absent" from "stop at the first absent".
        file_put_contents($this->root . '/CONTRIBUTING.md', "# Contributing\n");

        $documents = new RoadmapDocFileCollector($this->root)->collect();

        self::assertSame(['CONTRIBUTING.md'], array_keys($documents));
    }

    public function testReturnsEmptyWhenNoDocumentExists(): void
    {
        self::assertSame([], new RoadmapDocFileCollector($this->root)->collect());
    }
}
