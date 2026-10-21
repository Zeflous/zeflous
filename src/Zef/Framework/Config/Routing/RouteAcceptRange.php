<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * One parsed Accept media range: the type and subtype (each possibly the
 * `*` wildcard), the quality the range carries and how specific it is.
 *
 * @internal
 */
final readonly class RouteAcceptRange
{
    public function __construct(
        public string $type,
        public string $subtype,
        public float $quality,
        public int $specificity,
    ) {
    }

    /**
     * Whether the range covers the media type: each side matches on
     * equality or on its own wildcard.
     */
    public function matches(string $type, string $subtype): bool
    {
        return ($this->type === '*' || $this->type === $type)
            && ($this->subtype === '*' || $this->subtype === $subtype);
    }
}
