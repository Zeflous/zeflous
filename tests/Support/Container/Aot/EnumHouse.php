<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

/** Fixture: an enum-typed parameter whose default the plan bakes verbatim. */
final class EnumHouse
{
    /**
     * @param array<string, mixed> $notes
     */
    public function __construct(
        public Tone $tone = Tone::CALM,
        public array $notes = [],
    ) {
    }
}
