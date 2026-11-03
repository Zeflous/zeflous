<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\View;

use Zef\Framework\View\ViewException;

/**
 * The native answer's data key corridor: the one seam that judges
 * which bag keys may become the template's variables. The corridor
 * is grammar, not enumeration -- a key must be a letter-led
 * variable spelling -- because the scope the render opens carries
 * the engine's own reserved names, and the reserved corridor is the
 * leading underscore the grammar refuses by shape: the template's
 * own handle on the engine (`$_view`) and the render scope's own
 * carriers all live there, so a bag key can never spell a collision
 * with them (`EXTR_SKIP` in the scope is the belt-and-braces second
 * wall: even a key some future corridor change let through could not
 * overwrite the engine's own scope, and the grammar is what makes
 * that wall unreachable rather than load-bearing).
 *
 * Two keys the grammar alone cannot refuse are refused by name,
 * each with its own reason spelled: `this` is the engine scope's own
 * object binding (the closure the render executes is a static one
 * precisely so no `$this` exists to collide with, and the corridor
 * keeps a bag from reintroducing the spelling), and `GLOBALS` is the
 * one auto-global PHP refuses to rebind -- a bag key that could
 * reach the engine's own fatal instead of the lane's own typed
 * refusal would hand the template author a crash the family never
 * owned.
 *
 * The values are nobody's corridor: an honest `array<string, mixed>`
 * -- the template author's own vocabulary, the same trust every
 * plain-PHP template engine extends (the reference tree's two-tier
 * doctrine trusts the module's own views; a future Twig answer
 * inherits Twig's own context semantics over the same bag).
 */
final class ViewVariables
{
    /** The variable grammar: letter-led, then letters, digits or underscores. */
    private const string KEY_PATTERN = '/^[A-Za-z][A-Za-z0-9_]*$/D';

    /**
     * Guards the bag the caller hands against the corridor above.
     *
     * @param array<string, mixed> $data the bag whose keys become the template's variables
     *
     * @throws ViewException when a key is not a string, is not a letter-led
     *                       variable spelling, or carries a reserved name
     */
    public static function guard(array $data): void
    {
        foreach (array_keys($data) as $key) {
            self::assertKeySpellable($key);
        }
    }

    /**
     * @param mixed $key the runtime bag's own key -- the annotation promises a
     *                   string, the engine refuses to trust it (the `StorageDocumentRoot`
     *                   document-root entry posture: the mixed the runtime can hand
     *                   narrows inside, never at the call site)
     *
     * @throws ViewException when the key is refused by the corridor
     */
    private static function assertKeySpellable(mixed $key): void
    {
        if (!\is_string($key)) {
            throw new ViewException(
                'A view data key must be a string, got ' . get_debug_type($key) . '.',
            );
        }

        if ($key === 'this') {
            throw new ViewException(
                "The view data key 'this' is reserved by the engine's own scope.",
            );
        }

        if ($key === 'GLOBALS') {
            throw new ViewException(
                "The view data key 'GLOBALS' is reserved by the engine's own scope.",
            );
        }

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw new ViewException(
                "A view data key must be a letter-led variable spelling, got '"
                . $key . "'.",
            );
        }
    }
}
