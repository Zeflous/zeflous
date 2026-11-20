<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

/**
 * Fixture consumer for the argument grammar's literal escape: the
 * reference-shaped map and the plain scalar both arrive as DATA, so the
 * constructor's own parameters stay untyped -- the escape's payload is
 * whatever the declaration spelled, never the container's answer for it.
 */
final class EscapeHouse
{
    public function __construct(
        public mixed $window,
        public mixed $trim,
    ) {
    }
}
