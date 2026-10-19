<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Namespaces;

/**
 * The namespace lane's decoration fixture: a wrapper that carries its
 * own label and the service it wraps -- the shape a decorator entry
 * installs over a namespaced target, so the tree's prefix enumeration
 * can prove the ORIGINAL spelling answers the decorated instance.
 */
final readonly class NamespacedWrapper
{
    public function __construct(
        public string $label,
        public object $inner,
    ) {
    }
}
