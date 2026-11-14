<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use Zef\Framework\Container\AliasDefinition;
use Zef\Framework\Container\ServiceDefinition;

/**
 * The dump side of the artifact's alias ride index: the planned
 * definition set's aliases, one map entry per alias declaration --
 * the identifier whose singleton memo is the alias's own slot, and
 * the target identifier a re-registration must reach through (#770).
 *
 * The map bakes for the same reason the lazy map bakes ({@see
 * AotLazies}): the load lane cannot re-derive it. The compile lane
 * marks its rides from the *planned* set -- post-decoration, where a
 * decorated alias's own declaration has been re-homed under its
 * `@decor:` base identifier -- and rebuilding that walk from the
 * parsed declarations would mark the pre-surgery identifiers instead,
 * leaving the re-homed lane's memo slot exactly the stale-answer the
 * ride mark exists to forget. Baking the planned walk's answer is the
 * one honest spelling ({@see AotArtifact::aliasRides()}), and the
 * digest -- which folds the artifact generation the envelope now
 * carries this member at -- proves the baked map still describes the
 * live configuration.
 */
final readonly class AotAliasRides
{
    /**
     * Answers the planned definition set's alias ride index.
     *
     * @param list<ServiceDefinition> $definitions the pipeline's planned set
     *
     * @return array<string, string> the alias ride index, keyed by alias
     *                               identifier in declaration order
     */
    public static function all(array $definitions): array
    {
        /** @var array<string, string> $aliasRides */
        $aliasRides = [];

        foreach ($definitions as $definition) {
            if (!$definition instanceof AliasDefinition) {
                continue;
            }

            $aliasRides[$definition->id] = $definition->target;
        }

        return $aliasRides;
    }
}
