<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Override;
use Psr\Container\ContainerInterface;

/**
 * The subset locator (ROADMAP area 10's "service locator": a light
 * sub-container holding a subset of services for on-demand
 * consumption) -- a read-only PSR-11 *view* over the booted
 * container, not a copy of it.
 *
 * The subset is fixed at construction and fail-closed: every asked
 * identifier must already be an identifier the container can answer
 * (`has()` -- a registration or a covering fallback), and the boot's
 * own `@`-prefixed machinery identifiers are not the host's to hand
 * around. Resolution always *delegates* to the container -- the same
 * lanes, the same memoisation (a singleton the locator answers is
 * the singleton the container keeps), the same lazy deferral (a
 * marked identifier answers the native proxy), the same refusals an
 * un-restricted `get()` would take.
 *
 * What the locator adds is the boundary: an identifier outside the
 * subset refuses through the PSR-11 miss family with the subset's own
 * spelling, so a consumer holding the view cannot reach for services
 * it was not given -- the restricted hand-off is the point of the
 * subset, and the boundary is enforced, not advisory.
 */
final readonly class ContainerLocator implements ContainerInterface
{
    /** @var array<string, true> the subset, one key per asked identifier, in ask order */
    private array $ids;

    /**
     * Constructs one locator over the identifiers the host names,
     * every ask validated by the locator's own grammar ({@see
     * LocatorAsks}): non-empty, an identifier the container can
     * answer, and not the boot's own machinery, repeats collapsed --
     * the subset fixed the moment the locator exists.
     */
    public function __construct(
        private Container $container,
        string ...$ids,
    ) {
        $this->ids = LocatorAsks::validated($container, ...$ids);
    }

    /**
     * Answers the identifier's service, delegated to the container's
     * own lanes -- or refuses through the PSR-11 miss family when the
     * identifier is outside the subset.
     */
    #[Override]
    public function get(string $id): mixed
    {
        if (!isset($this->ids[$id])) {
            throw LocatorRefusals::outsideTheSubset($id);
        }

        return $this->container->get($id);
    }

    /**
     * Whether this locator can answer the identifier: inside the
     * subset *and* still carried by the container (PSR-11's letter:
     * whether `get()` will refuse).
     */
    #[Override]
    public function has(string $id): bool
    {
        return isset($this->ids[$id]) && $this->container->has($id);
    }

    /**
     * The subset's identifiers, in ask order, one member per asked
     * identifier.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->ids);
    }
}
