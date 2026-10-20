<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The lazy lane's runtime registry: the identifiers the boot marked
 * lazy, each with the class its native lazy proxy stands in for.
 *
 * The registration surface ({@see FactoryRegistrations}) stays
 * lazy-ignorant -- the compiled factories are the same eager closures
 * they always were -- and the resolution lanes consult this registry
 * alone ({@see ServiceResolutions}): an identifier the registry
 * carries resolves to a proxy that defers its guarded factory call to
 * first use, and an identifier it does not carries resolves exactly
 * as it always did (the empty registry's fast path is the unchanged
 * lane, the {@see Interceptions} empty-registry posture).
 *
 * The marks are the boot's own step, written after the install loop
 * on both lanes ({@see ContainerRegistrations::lazyMarks()}), and a
 * runtime re-registration through `set()` forgets them with the memo
 * ({@see ServiceStore::set()}): the eager surface re-registers
 * eagerly, and the marking step is the boot's to repeat, not the
 * store's to guess at.
 */
final class LazyIdentities
{
    /** @var array<string, class-string> */
    private array $classes = [];

    /**
     * The class the identifier's proxy stands in for, or null when
     * the identifier is not lazy.
     *
     * @return null|class-string
     */
    public function classOf(string $id): ?string
    {
        return $this->classes[$id] ?? null;
    }

    /**
     * Every mark the registry holds, keyed by identifier -- the
     * inventory's own read over the whole set ({@see
     * ContainerInventory::lazy()}), machinery identifiers included:
     * the registry stores, the read side chooses what to present.
     *
     * @return array<string, class-string>
     */
    public function marked(): array
    {
        return $this->classes;
    }

    /**
     * Marks the identifier lazy behind the class.
     *
     * Re-marking with the same class is the idempotent spelling the
     * boot's own repeat would take; re-marking with a different class
     * is the disagreement the registry refuses ({@see LazyRefusals::classDisagreement()}).
     *
     * @param class-string $class
     */
    public function mark(string $id, string $class): void
    {
        $marked = $this->classes[$id] ?? null;

        if ($marked !== null && $marked !== $class) {
            throw LazyRefusals::classDisagreement($id, $marked, $class);
        }

        $this->classes[$id] = $class;
    }

    /**
     * Forgets the identifier's mark -- the `set()` re-registration's
     * own clearing step, beside the memo's.
     */
    public function forget(string $id): void
    {
        unset($this->classes[$id]);
    }
}
