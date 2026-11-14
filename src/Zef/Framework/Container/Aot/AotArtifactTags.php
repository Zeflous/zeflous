<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The artifact's tag index validation: the per-member walk's own
 * unit, the same split the identifier lists ({@see AotArtifactIds}),
 * the lazy map ({@see AotArtifactLazies}) and the alias ride index
 * ({@see AotArtifactRides}) already take.
 *
 * The map's promises, validated fail-closed: a map of non-empty tag
 * strings, each naming a list of identifiers the identifier-list
 * validator itself proves -- a torn or hand-edited artifact cannot
 * half-boot with a tag locator entry pointing at identifiers the
 * install loop never knew.
 */
final readonly class AotArtifactTags
{
    /**
     * Validates the artifact's tag index member.
     *
     * @param mixed $tags the payload's `tags` member
     *
     * @return array<string, list<string>> the validated tag index
     */
    public static function validated(string $file, mixed $tags): array
    {
        if (!\is_array($tags)) {
            throw AotRefusals::artifactShape($file, 'the tag index must be a map');
        }

        $validated = [];

        foreach (array_keys($tags) as $tag) {
            if (!\is_string($tag) || $tag === '') {
                throw AotRefusals::artifactShape($file, 'the tag index keys must be non-empty tag strings');
            }

            $validated[$tag] = AotArtifactIds::validated($file, "the tag index for '{$tag}'", $tags[$tag]);
        }

        return $validated;
    }
}
