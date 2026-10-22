<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

/** Fixture: an object default the artifact boundary cannot render -- the degrade seam's shape. */
final class UnrenderableHouse
{
    public function __construct(
        public ?UnboundSink $sink = new PlainSink(),
    ) {
    }
}
