<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Namespaces;

use Closure;
use Zef\Framework\Container\Container;

/**
 * The namespace fallback lane's fixture factory: the static spellings
 * the closure-shaped registrations lean on, each answering a product
 * built from the identifier the miss carried -- the data a fallback
 * factory exists to receive.
 */
final readonly class FallbackFactories
{
    /**
     * Answers the missed identifier as the product's label -- the
     * simplest honest fallback: one product per identifier.
     */
    public static function forIdentifier(Container $container, string $id): NamespacedService
    {
        return new NamespacedService($id);
    }

    /**
     * Answers the missed identifier with the prefix's own label, the
     * shape a re-registered factory takes over from an earlier one --
     * the replacement the memo-forgetting tests observe.
     *
     * @return Closure(Container, string): NamespacedService
     */
    public static function withLabel(string $label): Closure
    {
        return static fn (Container $container, string $id): NamespacedService => new NamespacedService($label);
    }
}
