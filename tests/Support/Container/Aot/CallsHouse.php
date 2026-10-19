<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Aot;

/**
 * Fixture consumer for the method-call injection the rendered closure
 * delegates: each call records what it received, so the artifact lane's
 * instance carries the same post-construction state the full lane's
 * instance does.
 */
final class CallsHouse
{
    public ?Roof $roof = null;

    public string $weather = '';

    public int $degrees = 0;

    public function __construct(
        public Window $window,
    ) {
    }

    public function addRoof(Roof $roof): void
    {
        $this->roof = $roof;
    }

    public function noteWeather(string $condition, int $degrees): void
    {
        $this->weather = $condition;
        $this->degrees = $degrees;
    }
}
