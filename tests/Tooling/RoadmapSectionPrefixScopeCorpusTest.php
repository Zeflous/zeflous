<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\RoadmapSectionPrefix;

/**
 * The pairing corpus of the roadmap section prefix resolver.
 *
 * Issue #576 (the C-4 deferral of the round-2 audit, #554) deferred the
 * first-match-wins pairing until a corpus existed, because the resolver
 * feeds the cross-section contradiction rule of the live roadmap gate: a
 * pairing change alters which section's merged-row count a "not started"
 * bullet is checked against. This corpus is the safety net -- it pins the
 * resolver's answers over the pairing shapes the documents can produce,
 * including the twin-prefix shape the live `ROADMAP-STATUS.md` already
 * carries (`## HTTP Layer` beside `## HTTP Layer -- "Zero-Library Shim"`),
 * so any change to the resolution order lands as a visible flip of a named
 * corpus entry rather than as a silent re-pairing.
 *
 * The corpus is the ratchet, in the same spirit as the PHPStan baseline
 * gate ({@see BaselineGate}): each case's key names the family it belongs
 * to, and the inventory test pins the per-family counts so an entry cannot
 * appear or disappear without the counts moving in the same diff.
 *
 * @internal
 */
#[CoversClass(RoadmapSectionPrefix::class)]
final class RoadmapSectionPrefixScopeCorpusTest extends TestCase
{
    /**
     * @dataProvider provideThePairingScopeCorpus
     *
     * @param array<string, int> $sectionRows
     */
    #[DataProvider('provideThePairingScopeCorpus')]
    public function testThePairingScope(string $area, array $sectionRows, ?string $expected): void
    {
        self::assertSame($expected, RoadmapSectionPrefix::matching($area, $sectionRows));
    }

    /**
     * The ratchet odometer: the corpus is complete when the family counts
     * hold, so a pairing change cannot land quietly.
     */
    public function testTheCorpusCountsAreTheRatchet(): void
    {
        $families = array_count_values(
            array_map(
                static fn (string $case): string => explode(':', $case, 2)[0],
                array_keys([...self::provideThePairingScopeCorpus()]),
            ),
        );

        self::assertSame(2, $families['pairs']);
        self::assertSame(2, $families['clean']);
        self::assertSame(2, $families['gap']);
        self::assertSame(6, array_sum($families));
    }

    /**
     * The pairing-scope corpus: one area over one section map per case, with
     * the section the resolver must answer today. The `gap:` entries pin the
     * first-match-wins blind spots exactly as issue #576 received them.
     *
     * @return iterable<non-empty-string, array{string, array<string, int>, ?string}>
     */
    public static function provideThePairingScopeCorpus(): iterable
    {
        yield 'pairs: the section that prefixes the area' => [
            'HTTP Layer (Zero-Library Shim)',
            ['httplayer' => 3],
            'httplayer',
        ];

        yield 'pairs: the live section map reads the shim bullet as the HTTP section' => [
            // The live ROADMAP-HISTORY.md section map: the shim has no
            // numbered section of its own, so the bullet's area belongs to
            // the one HTTP section that exists.
            'HTTP Layer (Zero-Library Shim)',
            ['container' => 15, 'httplayer' => 25, 'errorhandling' => 6],
            'httplayer',
        ];

        yield 'clean: an area no section prefixes' => [
            'Widgets',
            ['httplayer' => 3],
            null,
        ];

        yield 'clean: the empty section key is ignored' => [
            'Widgets',
            ['' => 3],
            null,
        ];

        yield 'gap: the shorter twin wins by document order' => [
            // Two sections where one normalised title prefixes the other:
            // the resolver walks the map in insertion order and the first
            // match returns, so the shorter section answers even when the
            // area names the longer one's work.
            'HTTP Layer (Shim Work)',
            ['httplayer' => 25, 'httplayershimwork' => 2],
            'httplayer',
        ];

        yield 'gap: the longer twin wins by document order' => [
            // The same shape with the rows reversed: the answer flips with
            // the map's order, which is the order-sensitivity the gap names.
            'HTTP Layer (Shim Work)',
            ['httplayershimwork' => 2, 'httplayer' => 25],
            'httplayershimwork',
        ];
    }
}
