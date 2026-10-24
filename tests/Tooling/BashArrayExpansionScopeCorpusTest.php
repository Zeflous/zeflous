<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\BashArrayExpansionDetector;

/**
 * The detection-scope corpus of the bash array expansion detector.
 *
 * Issue #576 (the C-4 deferral of the round-2 audit, #554) deferred every
 * detection-scope widening of the live gates until a corpus existed: widening
 * a detector that runs on every pull request without one risks breaking CI
 * for the whole repository. This corpus is that safety net. It enumerates the
 * escape forms the detector's grammar can meet -- the forms it catches, the
 * forms it must keep passing, and the forms it currently lets slip -- so any
 * change to the detection logic lands as a visible flip of a named corpus
 * entry rather than as a silent verdict change.
 *
 * The corpus is the ratchet, in the same spirit as the PHPStan baseline
 * gate ({@see BaselineGate}): each case's key names the family it belongs to
 * (`detected:`, `clean:`, `gap:`), and the inventory test below pins the
 * per-family counts. A gap entry is an accepted blind spot documented by
 * name, not an accident: removing one (by fixing the detection, or by
 * deciding the form is out of scope) must update the inventory counts in the
 * same diff, which is exactly what makes the change conscious.
 *
 * @internal
 */
#[CoversClass(BashArrayExpansionDetector::class)]
final class BashArrayExpansionScopeCorpusTest extends TestCase
{
    /**
     * @dataProvider provideTheDetectionScopeCorpus
     */
    #[DataProvider('provideTheDetectionScopeCorpus')]
    public function testTheDetectionScope(string $contents, array $expected): void
    {
        self::assertSame($expected, BashArrayExpansionDetector::failures('corpus.yml', $contents));
    }

    /**
     * The ratchet odometer: the corpus is complete when the three family
     * counts hold, so an entry cannot appear or disappear -- a gap cannot be
     * quietly forgotten, a fix cannot quietly widen -- without this test
     * going red until the counts are updated in the same diff.
     */
    public function testTheCorpusCountsAreTheRatchet(): void
    {
        $families = array_count_values(
            array_map(
                static fn (string $case): string => explode(':', $case, 2)[0],
                array_keys([...self::provideTheDetectionScopeCorpus()]),
            ),
        );

        self::assertSame(4, $families['detected']);
        self::assertSame(5, $families['clean']);
        self::assertSame(4, $families['gap']);
        self::assertSame(13, array_sum($families));
    }

    /**
     * The detection-scope corpus: one workflow fragment per escape form, with
     * the verdict the detector must answer today. The `gap:` entries pin the
     * C-4 blind spots exactly as issue #576 received them.
     *
     * @return iterable<non-empty-string, array{string, list<string>}>
     */
    public static function provideTheDetectionScopeCorpus(): iterable
    {
        $failure = ['corpus.yml expands a bash array without [@] (word-splitting).'];

        yield 'detected: the bare braced expansion of a declared array' => [
            "required=(\"CI Static\" \"CI Coverage\")\nfor name in \${required}; do :; done\n",
            $failure,
        ];

        yield 'detected: the bare braced expansion among several declared arrays' => [
            "lanes=(\"a\" \"b\")\nnames=(\"x y\" \"z\")\n"
            . "for n in \"\${lanes[@]}\"; do echo \${names}; done\n",
            $failure,
        ];

        yield 'detected: the quoted bare braced expansion of a declared array' => [
            // "${required}" in quotes still answers only the first element,
            // the same disease the unquoted spelling carries.
            "required=(\"a b\")\necho \"\${required}\"\n",
            $failure,
        ];

        yield 'detected: the expansion keeps its verdict under a full-line comment above it' => [
            "# a bare \${required} would word-split\n"
            . "required=(\"CI Static\")\nfor name in \${required}; do :; done\n",
            $failure,
        ];

        yield 'clean: the quoted at-expansion' => [
            "required=(\"CI Static\")\nfor name in \"\${required[@]}\"; do :; done\n",
            [],
        ];

        yield 'clean: the indexed expansion' => [
            "required=(\"CI Static\")\necho \"\${required[0]}\"\n",
            [],
        ];

        yield 'clean: the braced expansion of a variable that is not a declared array' => [
            "for name in \${GITHUB_SHA}; do :; done\n",
            [],
        ];

        yield 'clean: a bare expansion mentioned only in a full-line comment' => [
            "# a bare \${required} would word-split; the array form is used below\n"
            . "required=(\"CI Static\")\nfor name in \"\${required[@]}\"; do :; done\n",
            [],
        ];

        yield 'clean: a declared array that is never expanded' => [
            "required=(\"CI Static\")\necho ok\n",
            [],
        ];

        yield 'gap: an unbraced expansion of a declared array slips through' => [
            // The word-splitting defect does not need the braces: a bare
            // $required word-splits exactly like ${required} does, but the
            // detection grammar only reads the braced spelling.
            "required=(\"CI Static\")\nfor name in \$required; do :; done\n",
            [],
        ];

        yield 'gap: a quoted unbraced expansion of a declared array slips through' => [
            "required=(\"CI Static\")\necho \"\$required\"\n",
            [],
        ];

        yield 'gap: an array first declared with += is not read as a declaration' => [
            // Bash grows an array with name+=(...) too -- including from
            // nothing -- and the declaration grammar only reads `name=(`,
            // so an append-only array's expansions escape the intersect.
            "required+=(\"CI Static\")\nfor name in \${required}; do :; done\n",
            [],
        ];

        yield 'gap: a trailing comment that mentions the bare expansion flags a clean file' => [
            // Only full-line comments are stripped: a correct loop with an
            // explanatory trailing comment still trips the detector, the
            // false positive the corpus exists to make visible.
            "required=(\"CI Static\")\n"
            . "for name in \"\${required[@]}\"; do :; done # a bare \${required} would word-split\n",
            $failure,
        ];
    }
}
