<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\View\ViewRenderDepth;
use Zef\Framework\View\ViewException;
use Zef\Framework\View\ViewName;

/**
 * Pins the nesting ledger's exact arithmetic: sixty-four nests are
 * the ceiling the engine climbs, the sixty-fifth names the depth it
 * refused, a leave steps the corridor back (the ledger is reusable
 * the moment its finally returns), and every engine instance owns
 * its own counter -- the per-engine declaration the composition's
 * own doctrine spells.
 *
 * @internal
 */
#[CoversClass(ViewRenderDepth::class)]
final class ViewRenderDepthTest extends TestCase
{
    public function testSixtyFourNestsAreClimbed(): void
    {
        $ledger = new ViewRenderDepth();
        $name = new ViewName('deep');

        for ($nest = 0; $nest < 64; ++$nest) {
            $ledger->enter($name);
        }

        $this->addToAssertionCount(1);
    }

    public function testTheSixtyFifthNestIsRefused(): void
    {
        $ledger = new ViewRenderDepth();
        $name = new ViewName('deep');

        for ($nest = 0; $nest < 64; ++$nest) {
            $ledger->enter($name);
        }

        try {
            $ledger->enter($name);
            self::fail('The sixty-fifth nest was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                'View rendering nesting of 64 exceeded while rendering \'deep\'.',
                $viewException->getMessage(),
            );
        }
    }

    public function testALeaveStepsTheCorridorBack(): void
    {
        $ledger = new ViewRenderDepth();
        $name = new ViewName('deep');

        for ($nest = 0; $nest < 64; ++$nest) {
            $ledger->enter($name);
        }

        $ledger->leave();
        $ledger->enter($name);
        $ledger->leave();
        $ledger->leave();

        $this->addToAssertionCount(1);
    }

    public function testTheCeilingIsPerEngineInstance(): void
    {
        $first = new ViewRenderDepth();
        $second = new ViewRenderDepth();
        $name = new ViewName('deep');

        for ($nest = 0; $nest < 64; ++$nest) {
            $first->enter($name);
        }

        for ($nest = 0; $nest < 64; ++$nest) {
            $second->enter($name);
        }

        $this->addToAssertionCount(1);
    }
}
