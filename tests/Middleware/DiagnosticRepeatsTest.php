<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Middleware\DiagnosticRepeats;

/**
 * The repeat ledger's own battery: the signature census and the bound
 * the diagnostic lane's fold rides on. Every component of the
 * signature -- level, file, line, message -- is pinned to distinguish
 * its own repeats, the separators are pinned to keep the components
 * from bleeding into one another (a pair whose concatenated neighbours
 * collide under a dropped separator must still record verbatim), and
 * the ledger's bound is pinned to degrade to no-dedup past its
 * capacity rather than to unbounded memory.
 *
 * @internal
 */
#[CoversClass(DiagnosticRepeats::class)]
final class DiagnosticRepeatsTest extends TestCase
{
    public function testTheFirstOccurrenceRecordsVerbatimAndTheRepeatIsSilent(): void
    {
        $diagnosticRepeats = new DiagnosticRepeats();

        self::assertSame(
            'The loop raised.',
            $diagnosticRepeats->verdict('warning', 'The loop raised.', '/app/a.php', 7),
        );
        self::assertNull($diagnosticRepeats->verdict('warning', 'The loop raised.', '/app/a.php', 7));
    }

    public function testTheCadenceSummaryNamesTheSuppressedCount(): void
    {
        $diagnosticRepeats = new DiagnosticRepeats();
        $verdict = null;

        for ($i = 0; $i < 101; ++$i) {
            $verdict = $diagnosticRepeats->verdict('warning', 'The loop raised.', '/app/a.php', 7);
        }

        self::assertSame('The loop raised. [repeated 100 more times]', $verdict);
    }

    public function testTheLevelDistinguishesItsOwnSignatures(): void
    {
        $diagnosticRepeats = new DiagnosticRepeats();

        self::assertSame('Same text.', $diagnosticRepeats->verdict('warning', 'Same text.', '/app/a.php', 7));
        self::assertSame('Same text.', $diagnosticRepeats->verdict('error', 'Same text.', '/app/a.php', 7));
    }

    public function testTheFileDistinguishesItsOwnSignatures(): void
    {
        $diagnosticRepeats = new DiagnosticRepeats();

        self::assertSame('Same text.', $diagnosticRepeats->verdict('warning', 'Same text.', '/app/a.php', 7));
        self::assertSame('Same text.', $diagnosticRepeats->verdict('warning', 'Same text.', '/app/b.php', 7));
    }

    public function testTheLineDistinguishesItsOwnSignatures(): void
    {
        $diagnosticRepeats = new DiagnosticRepeats();

        self::assertSame('Same text.', $diagnosticRepeats->verdict('warning', 'Same text.', '/app/a.php', 7));
        self::assertSame('Same text.', $diagnosticRepeats->verdict('warning', 'Same text.', '/app/a.php', 8));
    }

    public function testTheMessageDistinguishesItsOwnSignatures(): void
    {
        $diagnosticRepeats = new DiagnosticRepeats();

        self::assertSame('First text.', $diagnosticRepeats->verdict('warning', 'First text.', '/app/a.php', 7));
        self::assertSame('Second text.', $diagnosticRepeats->verdict('warning', 'Second text.', '/app/a.php', 7));
    }

    /**
     * The level/file seam: a pair whose concatenated level and file
     * collide ('i' + 'nfo' against 'in' + 'fo') still answers two
     * signatures -- the separator between the components keeps the
     * census injective, and a dropped or doubled separator would fold
     * the second occurrence into the first's silence.
     */
    public function testTheLevelAndTheFileStaySeparatedInTheSignature(): void
    {
        $diagnosticRepeats = new DiagnosticRepeats();

        self::assertSame('Same text.', $diagnosticRepeats->verdict('i', 'Same text.', 'nfo', 1));
        self::assertSame('Same text.', $diagnosticRepeats->verdict('in', 'Same text.', 'fo', 1));
    }

    /**
     * The file/line seam: 'p' + line 23 concatenates to the same bytes
     * as 'p2' + line 3, and the separator between them keeps the two
     * signatures apart.
     */
    public function testTheFileAndTheLineStaySeparatedInTheSignature(): void
    {
        $diagnosticRepeats = new DiagnosticRepeats();

        self::assertSame('Same text.', $diagnosticRepeats->verdict('info', 'Same text.', 'p', 23));
        self::assertSame('Same text.', $diagnosticRepeats->verdict('info', 'Same text.', 'p2', 3));
    }

    /**
     * The line/message seam: line 1 + '2q' concatenates to the same
     * bytes as line 12 + 'q', and the separator between them keeps the
     * two signatures apart.
     */
    public function testTheLineAndTheMessageStaySeparatedInTheSignature(): void
    {
        $diagnosticRepeats = new DiagnosticRepeats();

        self::assertSame('2q', $diagnosticRepeats->verdict('info', '2q', '/app/a.php', 1));
        self::assertSame('q', $diagnosticRepeats->verdict('info', 'q', '/app/a.php', 12));
    }

    /**
     * The ledger's bound: the two-hundred-fifty-sixth distinct
     * signature is still remembered (its repeat stays silent), the
     * next one beyond the bound is never remembered -- every one of its
     * occurrences records verbatim, the degraded no-dedup posture --
     * never an unbounded ledger.
     */
    public function testASignatureBeyondTheBoundRecordsVerbatimForever(): void
    {
        $diagnosticRepeats = new DiagnosticRepeats();

        for ($line = 1; $line <= 256; ++$line) {
            $diagnosticRepeats->verdict('info', 'filler', '/app/a.php', $line);
        }

        self::assertNull(
            $diagnosticRepeats->verdict('info', 'filler', '/app/a.php', 256),
            'the last signature inside the bound is still remembered',
        );

        self::assertSame('the overflow', $diagnosticRepeats->verdict('info', 'the overflow', '/app/a.php', 257));
        self::assertSame(
            'the overflow',
            $diagnosticRepeats->verdict('info', 'the overflow', '/app/a.php', 257),
            'a signature beyond the bound is never remembered, so every occurrence records verbatim',
        );
    }

    public function testAResetOpensAFreshWindow(): void
    {
        $diagnosticRepeats = new DiagnosticRepeats();

        $diagnosticRepeats->verdict('warning', 'The loop raised.', '/app/a.php', 7);
        self::assertNull($diagnosticRepeats->verdict('warning', 'The loop raised.', '/app/a.php', 7));

        $diagnosticRepeats->reset();

        self::assertSame(
            'The loop raised.',
            $diagnosticRepeats->verdict('warning', 'The loop raised.', '/app/a.php', 7),
        );
    }
}
