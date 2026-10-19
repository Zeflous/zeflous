<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

/**
 * The interface proof's refusal carrier: a plain class a knob entry
 * can name (class_exists holds) that implements no interception
 * contract -- the materialisation's not-implementing refusal names
 * the entry and the contract it fell short of.
 */
final readonly class ShapelessService
{
    public function __construct(
        public string $value = 'shapeless',
    ) {
    }
}
