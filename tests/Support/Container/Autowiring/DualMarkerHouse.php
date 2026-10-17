<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Autowiring;

/** Fixture: implements both markers -- two rules can collide on it. */
final class DualMarkerHouse implements MarkerA, MarkerB
{
}
