<?php

declare(strict_types=1);

namespace Zef\Test\Support\Routing;

/**
 * A route `meta` value object that CAN cross the compiled route tree
 * cache: `var_export()` renders it as a `::__set_state()` call, and the
 * class answers it, so the artifact's `require` rebuilds the value the
 * table carried ({@see \Zef\Framework\Config\Routing\RouteTreeCache}).
 *
 * The reconstruction is a NEW instance by construction -- the loaded tree
 * never shares identity with the dumped one -- so the value answers its
 * own equality vocabulary instead.
 */
final readonly class RouteMetaStamp
{
    /**
     * @param non-empty-string $tier
     */
    public function __construct(
        private string $tier,
    ) {
    }

    /**
     * The replay arm the cache's artifact format rests on: the properties
     * `var_export()` recorded, in the one shape this class owns.
     *
     * @param array{tier: non-empty-string} $properties
     */
    public static function __set_state(array $properties): self
    {
        return new self($properties['tier']);
    }

    /**
     * @return non-empty-string
     */
    public function tier(): string
    {
        return $this->tier;
    }
}
