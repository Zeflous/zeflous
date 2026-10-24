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
 * same diff, which is exactly what makes the change conscious. The detection
 * widening of issue #576 flipped three gap entries to detections and the
 * trailing-comment false positive to clean in the same diff that widened
 * the grammar; the two gap entries that remain are the documented
 * approximations of a line discipline that is not a bash tokenizer.
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

        self::assertSame(7, $families['detected']);
        self::assertSame(6, $families['clean']);
        self::assertSame(2, $families['gap']);
        self::assertSame(15, array_sum($families));
    }

    /**
     * The detection-scope corpus: one workflow fragment per escape form, with
     * the verdict the detector must answer today. The `gap:` entries are the
     * accepted approximations that remain after issue #576's widening -- a
     * line discipline without a bash tokenizer has exactly two residuals, and
     * both are documented by name.
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

        yield 'detected: the unbraced expansion of a declared array' => [
            // The word-splitting defect does not need the braces: a bare
            // $required word-splits exactly like ${required} does.
            "required=(\"CI Static\")\nfor name in \$required; do :; done\n",
            $failure,
        ];

        yield 'detected: the quoted unbraced expansion of a declared array' => [
            "required=(\"CI Static\")\necho \"\$required\"\n",
            $failure,
        ];

        yield 'detected: an array first declared with +=' => [
            // Bash grows an array with name+=(...) too -- including from
            // nothing -- so the declaration grammar reads the append form.
            "required+=(\"CI Static\")\nfor name in \${required}; do :; done\n",
            $failure,
        ];

        yield 'clean: a trailing comment that mentions the bare expansion no longer flags the file' => [
            "required=(\"CI Static\")\n"
            . "for name in \"\${required[@]}\"; do :; done # a bare \${required} would word-split\n",
            [],
        ];

        yield 'gap: a bare expansion inside a quoted string after an in-string hash stays invisible' => [
            // The comment strip is positional, not lexical: a hash preceded
            // by whitespace is stripped even inside a quoted string, so an
            // expansion behind it disappears with the "comment". Seeing
            // through this needs a bash tokenizer, not a line discipline.
            "required=(\"a b\")\necho \"note # \${required}\"\n",
            [],
        ];

        yield 'gap: a hash glued to a semicolon keeps its mention flagged' => [
            // The mirror residual: a bash comment may start at any word
            // boundary, and this one is glued to the semicolon, so the
            // whitespace-anchored strip leaves the mention in the code.
            "required=(\"CI Static\")\n"
            . "for name in \"\${required[@]}\"; do :; done;# a bare \${required} would word-split\n",
            $failure,
        ];
    }
}
