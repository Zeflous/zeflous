<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\RepeatedWordRun;
use Zef\Test\Support\Tooling\RoadmapPhraseRules;
use Zef\Test\Support\Tooling\RoadmapTableCells;

/**
 * @internal
 */
#[CoversClass(RoadmapPhraseRules::class)]
#[CoversClass(RepeatedWordRun::class)]
#[CoversClass(RoadmapTableCells::class)]
final class RoadmapPhraseRulesTest extends TestCase
{
    public function testReportsARepeatedPhraseInOneCell(): void
    {
        // The shipped defect: the PSR-7 `Uri` value object was named twice in
        // one evidence cell. The structural rules accept it; this rule must not.
        $document = "| Area | Status | Evidence |\n|---|---|---|\n"
            . "| HTTP | done | the PSR-7 `Uri` value object (#259) and the PSR-7 `Uri` value object (#259) |\n";
        $lines = explode("\n", $document);

        $failures = RoadmapPhraseRules::duplicatedPhrases('BROKEN.md', $lines);

        self::assertCount(1, $failures);
        self::assertSame(
            'BROKEN.md:3 repeats the phrase "the psr-7 `uri` value object #259" inside one cell.',
            $failures[0],
        );
    }

    public function testPassesOnACellWithoutARepeatedPhrase(): void
    {
        $document = "| Area | Status | Evidence |\n|---|---|---|\n"
            . "| HTTP | done | the PSR-7 `Uri` value object (#259) and the message half (#262) |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapPhraseRules::duplicatedPhrases('CLEAN.md', $lines));
    }

    public function testIgnoresARepeatShorterThanTheMinimumRun(): void
    {
        // A five-word repeat is ordinary prose, not a copy/paste defect.
        $document = "| Area | Status | Evidence |\n|---|---|---|\n"
            . "| HTTP | done | data found / notFound / methodNotAllowed with a sorted allowed list |\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapPhraseRules::duplicatedPhrases('CLEAN.md', $lines));
    }

    public function testReportsEveryDuplicatedCellInOneDocument(): void
    {
        $document = "| Area | Status | Evidence |\n|---|---|---|\n"
            . "| HTTP | done | the PSR-7 `Uri` value object (#259) and the PSR-7 `Uri` value object (#259) |\n"
            . "| HTTP | done | and the Fase 2 runtime adapter and the Fase 2 runtime adapter is live |\n";
        $lines = explode("\n", $document);

        $failures = RoadmapPhraseRules::duplicatedPhrases('BROKEN.md', $lines);

        self::assertCount(2, $failures);
        self::assertStringStartsWith('BROKEN.md:3 repeats the phrase', $failures[0]);
        self::assertStringStartsWith('BROKEN.md:4 repeats the phrase', $failures[1]);
    }

    public function testIgnoresNonTableLines(): void
    {
        $document = "the PSR-7 `Uri` value object (#259) and the PSR-7 `Uri` value object (#259)\n";
        $lines = explode("\n", $document);

        self::assertSame([], RoadmapPhraseRules::duplicatedPhrases('CLEAN.md', $lines));
    }
}
