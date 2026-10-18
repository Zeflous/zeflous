<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Parameters;

/** A class that exists but does not implement the provider interface. */
final class NotAProvider
{
    public function value(): mixed
    {
        return 'never-reached';
    }
}
