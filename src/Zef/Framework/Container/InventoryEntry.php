<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One declared service's inventory entry (ROADMAP area 12's "how each
 * service was built"): the recipe the boot spelled for the identifier
 * -- its shape and the shape's own members, its lifetime, its tags,
 * its method-call injections, its declared dependencies, and the lazy
 * mark the runtime holds for it -- answered as the plain data the
 * developer experience reads.
 *
 * The entry is a snapshot built per read ({@see ContainerInventory}):
 * the declaration half is the boot's own record (immutable by
 * construction -- a declaration is what the host spelled), while the
 * lazy half is read live from the registry the resolution lanes
 * consult ({@see LazyIdentities}), so a programmatic mark or a
 * `set()` re-registration's forgetting is reflected in the next read.
 */
final readonly class InventoryEntry
{
    /**
     * @param string            $id           the declared identifier
     * @param string            $shape        the declaration's shape: 'class', 'factory', 'alias' or 'value'
     * @param null|class-string $class        the class shape's own class
     * @param null|string       $factory      the factory shape's 'Target::method' spelling
     * @param null|string       $alias        the alias shape's target identifier
     * @param mixed             $value        the value shape's held value
     * @param string            $lifetime     the declaration's lifetime key
     * @param list<string>      $tags         the declaration's grouping labels
     * @param list<string>      $calls        the method-call injections' method names, in declaration order
     * @param null|class-string $lazy         the class the runtime's mark defers behind, null when eager
     * @param list<string>      $dependencies the declared service references (arguments
     *                                        and calls), in declaration order
     */
    public function __construct(
        public string $id,
        public string $shape,
        public ?string $class,
        public ?string $factory,
        public ?string $alias,
        public mixed $value,
        public string $lifetime,
        public array $tags,
        public array $calls,
        public ?string $lazy,
        public array $dependencies,
    ) {
    }
}
