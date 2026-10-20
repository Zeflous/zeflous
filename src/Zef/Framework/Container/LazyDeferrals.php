<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The lazy lane's rider over the resolution seam: the registry the
 * boot marked ({@see LazyIdentities}) consulted at the miss path, and
 * the native proxy answered for a marked identifier
 * ({@see LazyConstructions}) whose materialiser runs the guarded call
 * itself -- one unit so the resolution lane stays the three arms it
 * always was.
 *
 * The materialiser runs *the guarded call itself* -- the cycle guard,
 * the interception onion and the listeners all fire at
 * materialisation, exactly as they would have at resolution, just
 * later ({@see LazyMaterialisations} carries the step). The memo
 * holds the proxy (a singleton materialises once, through the one
 * proxy it keeps); a transient answers a fresh proxy per resolution,
 * each materialising its own instance.
 */
final readonly class LazyDeferrals
{
    public function __construct(
        private LazyIdentities $lazyIdentities = new LazyIdentities(),
        private LazyMaterialisations $lazyMaterialisations = new LazyMaterialisations(),
    ) {
    }

    /**
     * The registry the boot marked ({@see LazyIdentities}) -- the
     * store's own reach-through for the facade and the guarded mark
     * ({@see LazyMarking}).
     */
    public function identities(): LazyIdentities
    {
        return $this->lazyIdentities;
    }

    /**
     * Forgets the identifier's mark -- the `set()` re-registration's
     * own clearing step, beside the memo's.
     */
    public function forget(string $id): void
    {
        $this->lazyIdentities->forget($id);
    }

    /**
     * One resolution's own answer: the guarded call the lane always
     * took, or the native proxy the identifier's mark defers it
     * behind -- the only place the two spellings differ, the
     * materialiser running the same guarded call a lazy declaration
     * simply asks to run later ({@see LazyMaterialisations}).
     *
     * @param Closure(string, Container): mixed $guarded the lane's own
     *                                                   guarded call, embedded in the
     *                                                   proxy's materialiser
     */
    public function answering(string $id, Container $container, Closure $guarded): mixed
    {
        $lazyClass = $this->lazyIdentities->classOf($id);

        return $lazyClass === null
            ? $guarded($id, $container)
            : LazyConstructions::of(
                $lazyClass,
                fn (): object => $this->lazyMaterialisations->materialised($id, $container, $lazyClass, $guarded),
            );
    }
}
