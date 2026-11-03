<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\View;

use Zef\Framework\View\ViewException;
use Zef\Framework\View\ViewName;

/**
 * The native answer's rendering scope: the seam that opens the
 * output buffer, includes the resolved template inside an isolated
 * static closure, and answers the captured text -- the ONE place
 * the engine's buffering discipline lives.
 *
 * The closure is STATIC on purpose: no `$this` exists in a template
 * scope, so the engine's own object never reaches the author's
 * code, and the only names the template sees beyond its data are
 * the reserved carriers the corridor's grammar keeps
 * collision-free (`$_view` the face, `$_file` the resolved
 * address) and the data bag's own variables, extracted with
 * `EXTR_SKIP` so the reserved carriers survive even a key some
 * future corridor change let through -- the belt-and-braces second
 * wall the grammar makes unreachable rather than load-bearing.
 *
 * The BUFFER discipline is the fold's own law, spelled as level
 * accounting: the level before the open is the entry, the buffer
 * opened is the ONLY one the template may hold (`ob_get_level()`
 * must answer entry-plus-one when the include returns -- a template
 * that closed the engine's buffer or left one of its own open
 * disturbed the discipline, and the refusal fires BEFORE any
 * capture, so a template that closed the engine's buffer can never
 * steal the caller's -- the answer it would have captured is
 * discarded with the refusal), and the finally pops every level
 * above the entry back down, so a crash at ANY nesting depth leaves
 * no partial output and no open buffer behind. The discipline check
 * is also the `ob_start()` failure answer: a buffer that could not
 * open leaves the level at the entry, and the check refuses.
 */
final class ViewRenderScope
{
    /**
     * Renders one resolved template inside the isolated scope,
     * answering the captured text.
     *
     * @param string               $file the resolved template address {@see ViewTemplateFile::resolve()} answered
     * @param TemplateFace         $view the face the template holds
     * @param array<string, mixed> $data the guarded bag whose keys become the template's variables
     * @param ViewName             $name the identity the refusals name
     *
     * @throws ViewException when the template disturbs the output buffering
     *                       discipline
     */
    public static function render(string $file, TemplateFace $view, array $data, ViewName $name): string
    {
        $entry = ob_get_level();

        ob_start();

        try {
            $answer = (static function (string $_file, TemplateFace $_view, array $_data): string {
                extract($_data, \EXTR_SKIP);
                unset($_data);

                include $_file;

                return (string) ob_get_contents();
            })($file, $view, $data);

            if (ob_get_level() !== $entry + 1) {
                throw new ViewException(
                    'The view template \'' . $name->spelling() . '\' disturbed the'
                    . ' engine\'s output buffering discipline.',
                );
            }
        } finally {
            while (ob_get_level() > $entry) {
                ob_end_clean();
            }
        }

        return $answer;
    }
}
