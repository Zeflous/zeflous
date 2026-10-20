<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The lazy lane's materialiser step: the guarded call a native
 * proxy's first use runs, and the honest refusal a factory that
 * answers a non-object behind a lazy mark takes ({@see
 * LazyDeferrals} embeds this step in the proxy it answers).
 *
 * A native lazy proxy stands in for an object of the marked class --
 * PHP itself refuses, at materialisation, an answer that is not an
 * instance of it; this guard refuses the cruder non-object spelling
 * earlier, with the service's own identifier in the message.
 */
final readonly class LazyMaterialisations
{
    /**
     * @param class-string                      $lazyClass the class the proxy stands in for
     * @param Closure(string, Container): mixed $guarded   the lane's own guarded call
     */
    public function materialised(string $id, Container $container, string $lazyClass, Closure $guarded): object
    {
        $answer = $guarded($id, $container);

        if (!\is_object($answer)) {
            throw new DefinitionException(
                "Service '{$id}' is lazy behind the class '{$lazyClass}' but its factory answered "
                . get_debug_type($answer) . ' (a lazy proxy stands in for an object; drop the '
                . 'lazy declaration or answer an instance of the marked class).',
            );
        }

        return $answer;
    }
}
