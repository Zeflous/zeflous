<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\View\ViewRenderDepth;
use Zef\Framework\View\ViewException;
use Zef\Framework\View\ViewName;

/**
 * Pins the nesting ledger's exact arithmetic: the sixty-four climbs
 * the ceiling permits are observable only through the sixty-fifth's
 * refusal (the ledger owns no other answer), a leave steps the
 * corridor back -- the re-entry after a leave must climb again
 * before the same refusal fires -- and every engine instance owns
 * its own counter, the per-engine declaration the composition's own
 * doctrine spells.
 *
 * @internal
 */
#[CoversClass(ViewRenderDepth::class)]
final class ViewRenderDepthTest extends TestCase
{
    public function testTheSixtyFifthNestIsRefusedAfterSixtyFourClimbs(): void
    {
        $viewRenderDepth = new ViewRenderDepth();
        $viewName = new ViewName('deep');

        for ($nest = 0; $nest < 64; ++$nest) {
            $viewRenderDepth->enter($viewName);
        }

        try {
            $viewRenderDepth->enter($viewName);
            self::fail('The sixty-fifth nest was not refused.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "View rendering nesting of 64 exceeded while rendering 'deep'.",
                $viewException->getMessage(),
            );
        }
    }

    public function testALeaveStepsTheCorridorBack(): void
    {
        $viewRenderDepth = new ViewRenderDepth();
        $viewName = new ViewName('deep');

        for ($nest = 0; $nest < 64; ++$nest) {
            $viewRenderDepth->enter($viewName);
        }

        $viewRenderDepth->leave();
        $viewRenderDepth->enter($viewName);

        try {
            $viewRenderDepth->enter($viewName);
            self::fail('The re-climbed nest was not refused at the ceiling.');
        } catch (ViewException $viewException) {
            self::assertSame(
                "View rendering nesting of 64 exceeded while rendering 'deep'.",
                $viewException->getMessage(),
            );
        }
    }

    public function testTheCeilingIsPerEngineInstance(): void
    {
        $first = new ViewRenderDepth();
        $second = new ViewRenderDepth();
        $viewName = new ViewName('deep');

        for ($nest = 0; $nest < 64; ++$nest) {
            $first->enter($viewName);
        }

        for ($nest = 0; $nest < 64; ++$nest) {
            $second->enter($viewName);
        }

        try {
            $second->enter($viewName);
            self::fail("The second engine's own sixty-fifth nest was not refused.");
        } catch (ViewException $viewException) {
            self::assertSame(
                "View rendering nesting of 64 exceeded while rendering 'deep'.",
                $viewException->getMessage(),
            );
        }
    }
}
