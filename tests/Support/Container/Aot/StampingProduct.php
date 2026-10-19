<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

/**
 * The fixture product that records its stamp: the observable state the
 * method-call injection over a factory shape leaves behind.
 */
final class StampingProduct
{
    public string $serial = '';

    public function __construct(
        public Window $window,
    ) {
    }

    public function stamp(string $serial): void
    {
        $this->serial = $serial;
    }
}
