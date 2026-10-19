<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

/** A collaborator the method-call injection lane injects post-construction. */
final readonly class Roof
{
    public function __construct(
        public string $material = 'slate',
    ) {
    }
}
