<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The artifact's alias ride index validation: the per-member walk's
 * own unit, the same split the lazy map ({@see AotArtifactLazies})
 * and the identifier lists ({@see AotArtifactIds}) already take.
 *
 * The map's promises, validated fail-closed: a map (not a list, not
 * a scalar), non-empty string identifiers on both sides -- the load
 * lane marks its rides from this map ({@see AotBootLane}), so a torn
 * or hand-edited artifact cannot half-boot with an alias the
 * re-registration walk would reach through into a slot nobody
 * installed.
 */
final readonly class AotArtifactRides
{
    /**
     * Validates the artifact's alias ride index member.
     *
     * @param mixed $aliasRides the payload's `aliasRides` member
     *
     * @return array<string, string> the validated alias ride index
     */
    public static function validated(string $file, mixed $aliasRides): array
    {
        if (!\is_array($aliasRides)) {
            throw AotRefusals::artifactShape($file, 'the alias ride index must be a map');
        }

        $validated = [];

        foreach ($aliasRides as $aliasId => $targetId) {
            if (!\is_string($aliasId) || $aliasId === '') {
                throw AotRefusals::artifactShape(
                    $file,
                    'the alias ride index keys must be non-empty service identifiers',
                );
            }

            if (!\is_string($targetId) || $targetId === '') {
                throw AotRefusals::artifactShape(
                    $file,
                    "the alias ride index for '{$aliasId}' must name a non-empty target identifier",
                );
            }

            $validated[$aliasId] = $targetId;
        }

        return $validated;
    }
}
