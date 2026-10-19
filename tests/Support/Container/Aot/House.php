<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

/**
 * Fixture consumer for the instance shape's rendered closure: a
 * collaborator reference, a scalar literal and a nested array literal,
 * the three argument spellings the artifact's `new` expression inlines.
 */
final readonly class House
{
    /**
     * @param array<string, mixed> $trim the nested literal argument
     */
    public function __construct(
        public Window $window,
        public int $panes,
        public array $trim,
    ) {
    }
}
