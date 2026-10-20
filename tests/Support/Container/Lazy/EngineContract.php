<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Lazy;

/**
 * Fixture interface: a factory method declaring an interface return
 * type names no proxyable class, so a lazy declaration behind it is
 * refused at parse time (the concrete product class is the spelling
 * the declaration must carry instead).
 */
interface EngineContract
{
    public function thrust(): int;
}
