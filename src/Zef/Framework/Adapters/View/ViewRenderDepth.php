<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\View;

use Zef\Framework\View\ViewException;
use Zef\Framework\View\ViewName;

/**
 * The native answer's nesting ledger: the render-depth counter, the
 * nesting ceiling it refuses to climb past, and the enter/leave pair
 * the engine calls around every scope -- the ONE place the depth
 * lives, the same single-place bookkeeping posture the PDO adapter's
 * own transaction depth fold carries (a mutable fold behind a
 * readonly composition: the engine never reassigns its properties,
 * the counter mutates its own).
 *
 * The ceiling exists because the native answer's templates can
 * render templates through the engine's own face ({@see TemplateFace}),
 * and a template that renders itself -- by accident or by a cycle
 * two partials spell -- would otherwise recurse until the engine's
 * own memory answered first. The refusal names the name at the
 * crossing: the depth that was refused is the depth the operator
 * reads.
 *
 * The ceiling is per-engine by declaration: every engine instance
 * owns its own counter, exactly as every connection owns its own
 * savepoint depth -- the cross-engine recursion a template author
 * spells by holding two engines is author code the lane does not
 * police, the same trust the data bag carries.
 */
final class ViewRenderDepth
{
    /** The nesting ceiling: at most this many scopes deep, the self-recursive template's own guard. */
    private const int MAX_RENDERING = 64;

    private int $level = 0;

    /**
     * The level only ever moves one step per verb, so the exact
     * ceiling spelling and the comparison agree on every reachable
     * state -- the 65th nested render meets level 64.
     *
     * @throws ViewException when the nesting ceiling would be exceeded
     */
    public function enter(ViewName $viewName): void
    {
        if ($this->level === self::MAX_RENDERING) {
            throw new ViewException(
                'View rendering nesting of ' . self::MAX_RENDERING
                . " exceeded while rendering '" . $viewName->spelling() . "'.",
            );
        }

        ++$this->level;
    }

    /**
     * Steps one level back -- the caller's finally owns the call, so
     * every path (the answered render, the refused render, the
     * crashed render) leaves the ledger exactly where it found it.
     */
    public function leave(): void
    {
        --$this->level;
    }
}
