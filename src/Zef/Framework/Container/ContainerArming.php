<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\ContainerKnobs;

/**
 * The boot's interception arming: the one spelling of the call both
 * lanes make after their install loops and before their warmups (the
 * {@see ContainerRegistrations} posture -- one name for the step every
 * lane shares), so the order -- arm before the warmup, so an eagerly
 * constructed singleton rides the interception like any other
 * construction -- is spelled once, not twice.
 */
final readonly class ContainerArming
{
    /**
     * Arms the container's interception registry from the parsed knobs.
     */
    public static function interceptions(Container $container, ContainerKnobs $containerKnobs): void
    {
        $container->interceptions()->arm(
            $containerKnobs->interceptors,
            $containerKnobs->listeners,
            $container,
        );
    }
}
