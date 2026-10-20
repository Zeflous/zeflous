<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The artifact's lazy map validation: the per-member walk's own unit,
 * the same split the unreferenced report and the identifier lists
 * already take ({@see AotArtifactIds}).
 *
 * The map's promises, validated fail-closed: a map (not a list, not a
 * scalar), non-empty string identifiers, and class names the runtime
 * actually carries -- the load lane marks its lazy identities from
 * this map, so a torn or hand-edited artifact cannot half-boot with a
 * lazy mark pointing at a class PHP cannot instantiate a proxy of.
 */
final readonly class AotArtifactLazies
{
    /**
     * Validates the artifact's lazy map member.
     *
     * @param mixed $lazy the payload's `lazy` member
     *
     * @return array<string, class-string> the validated lazy map
     */
    public static function validated(string $file, mixed $lazy): array
    {
        if (!\is_array($lazy)) {
            throw AotRefusals::artifactShape($file, 'the lazy map must be a map');
        }

        $validated = [];

        foreach ($lazy as $id => $class) {
            if (!\is_string($id) || $id === '') {
                throw AotRefusals::artifactShape($file, 'the lazy map keys must be non-empty service identifiers');
            }

            if (!\is_string($class) || $class === '' || !class_exists($class)) {
                throw AotRefusals::artifactShape(
                    $file,
                    "the lazy map for '{$id}' must name an existing class",
                );
            }

            $validated[$id] = $class;
        }

        return $validated;
    }
}
