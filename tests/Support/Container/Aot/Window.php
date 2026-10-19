<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

/** A plain collaborator the AOT rendering lane's constructor calls carry. */
final readonly class Window
{
    public function __construct(
        public string $glazing = 'double',
    ) {
    }
}
