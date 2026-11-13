<?php

declare(strict_types=1);

namespace Zef\Test\Config\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Zef\Framework\Config\Loader\SortedDirectoryEntries;

/**
 * @internal
 */
#[CoversClass(SortedDirectoryEntries::class)]
final class SortedDirectoryEntriesTest extends TestCase
{
    public function testAnswersTheEntriesInSortedNameOrder(): void
    {
        // The entries arrive in a hand-built order no filesystem answered
        // for: the fold's contract is pure over the injected list, so its
        // kill owns no host -- the sorted answer is the whole behaviour.
        $entries = [
            new SplFileInfo('/probe/zeta.php'),
            new SplFileInfo('/probe/.hidden.php'),
            new SplFileInfo('/probe/alpha.php'),
            new SplFileInfo('/probe/mixed.php'),
            new SplFileInfo('/probe/a-notes.txt'),
        ];

        $sorted = SortedDirectoryEntries::inNameOrder($entries);

        self::assertSame(
            [
                '/probe/.hidden.php',
                '/probe/a-notes.txt',
                '/probe/alpha.php',
                '/probe/mixed.php',
                '/probe/zeta.php',
            ],
            array_map(static fn (SplFileInfo $file): string => $file->getPathname(), $sorted),
            'the fold orders the entries by filename, hidden entries first',
        );
    }

    public function testAnEmptyEntryListAnswersAnEmptyList(): void
    {
        self::assertSame([], SortedDirectoryEntries::inNameOrder([]));
    }

    public function testTheAnswerCarriesEveryEntryItWasGiven(): void
    {
        $entries = [
            new SplFileInfo('/probe/b.php'),
            new SplFileInfo('/probe/a.php'),
        ];

        self::assertCount(2, SortedDirectoryEntries::inNameOrder($entries));
    }
}
