<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use Zef\Framework\Contracts\ContainerException;

/**
 * Thrown when the compile/dump lane's machinery fails: an artifact that
 * refuses to validate, a value the artifact boundary cannot export, a
 * digest input that cannot fold, or a dump that cannot be laid down.
 *
 * The family's knob grammar refusals travel as
 * {@see \Zef\Framework\Container\DefinitionException} beside every
 * sibling knob's; this subtype carries the lane's *machinery* failures,
 * the way the autowiring lane's own subtype carries its specifics -- a
 * caller catching the contracts' {@see ContainerException} root catches
 * every way a container-side boot can refuse.
 */
final class AotException extends ContainerException
{
}
