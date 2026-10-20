<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The debug inventory (ROADMAP area 12's introspection read side):
 * the declarations the boot spelled, answered as data -- which
 * services exist, which shape each declared, what each depends on,
 * which tags they carry, and which identifiers the runtime has marked
 * lazy. The CLI analysis surface the roadmap's developer-experience
 * item consumes reads from here; the container's own contribution is
 * this read side, not the command line.
 *
 * Two clocks, both honest. The declaration half is the boot's own
 * record -- the parsed set the host spelled, frozen at the boot
 * (a later `set()` re-registration changes the runtime's answer, not
 * the declaration the boot recorded). The lazy half is live: the mark
 * registry the resolution lanes consult is read per entry, so a
 * programmatic {@see Container::markLazy()} and a `set()`'s forgetting
 * are both reflected in the next read. The boot's `@`-prefixed
 * machinery identifiers stay out of the lazy view the way they stay
 * out of every host-facing surface ({@see NamespaceTree}).
 *
 * Registered as the reserved `framework.inventory` service
 * ({@see ContainerServices::INVENTORY_SERVICE}), ahead of the
 * definitions, the `framework.tags` precedent's own family.
 */
final readonly class ContainerInventory
{
    /** @var array<string, ServiceDefinition> the boot's parsed declarations, keyed by identifier */
    private array $declarations;

    /**
     * @param list<ServiceDefinition> $declarations   the boot's parsed declaration set
     * @param LazyIdentities          $lazyIdentities the live mark registry
     */
    public function __construct(
        array $declarations,
        private LazyIdentities $lazyIdentities,
    ) {
        $keyed = [];

        foreach ($declarations as $declaration) {
            $keyed[$declaration->id] = $declaration;
        }

        $this->declarations = $keyed;
    }

    /**
     * The declared identifiers, in declaration order.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->declarations);
    }

    /**
     * One declared identifier's entry, or null when the boot spelled
     * no such declaration.
     */
    public function entry(string $id): ?InventoryEntry
    {
        $declaration = $this->declarations[$id] ?? null;

        return $declaration === null
            ? null
            : InventoryEntries::of($declaration, $this->lazyIdentities->classOf($id));
    }

    /**
     * The runtime's lazy marks: which identifiers defer behind which
     * proxy class, the boot's `@`-prefixed machinery excluded -- the
     * marks the host's identifiers carry, not the re-homed bases the
     * decoration lane built.
     *
     * @return array<string, class-string>
     */
    public function lazy(): array
    {
        $marks = [];

        foreach ($this->lazyIdentities->marked() as $id => $class) {
            if (str_starts_with($id, '@')) {
                continue;
            }

            $marks[$id] = $class;
        }

        return $marks;
    }
}
