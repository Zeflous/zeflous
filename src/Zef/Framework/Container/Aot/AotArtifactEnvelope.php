<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The artifact's envelope validation: the five keys the dump always
 * writes, exactly -- the structural promises that precede any
 * per-member or per-entry validation, split as their own unit the way
 * the entries and the identifier lists already are.
 *
 * The guards: the payload is a map, no unknown envelope key rides
 * along, every required key is present, and the digest is a non-empty
 * string. What answers is the raw member map with the digest proven --
 * the members' own validators ({@see AotArtifactIds},
 * {@see AotArtifact::validatedTags()}, {@see AotArtifactLazies::validated()},
 * {@see AotArtifactEntries}) take it from there.
 *
 * The {@see GENERATION} constant is the boundary's format version the
 * digest folds ({@see AotDigest::ARTIFACT_GENERATION}): an artifact of
 * an older generation is proven stale by the digest itself, so the
 * envelope's strict five-key contract never meets a four-key artifact
 * -- the strictness and the generation travel together, and a future
 * shape change bumps both in the same breath.
 */
final readonly class AotArtifactEnvelope
{
    /**
     * The envelope's format generation, folded into every digest
     * ({@see AotDigest::ARTIFACT_GENERATION} -- the two constants are
     * one number, spelled where each unit needs it).
     */
    public const int GENERATION = AotDigest::ARTIFACT_GENERATION;

    /**
     * The envelope the dump always writes, exactly.
     */
    private const array ENVELOPE = [
        'digest' => true,
        'unreferenced' => true,
        'tags' => true,
        'lazy' => true,
        'entries' => true,
    ];

    /**
     * Validates the payload's envelope and answers its member map.
     *
     * @param mixed $payload whatever the artifact file returned
     *
     * @return array{digest: string, unreferenced: mixed, tags: mixed, lazy: mixed, entries: mixed}
     */
    public static function validated(string $file, mixed $payload): array
    {
        if (!\is_array($payload)) {
            throw AotRefusals::artifactNotArray($file);
        }

        $unknown = array_diff_key($payload, self::ENVELOPE);
        if ($unknown !== []) {
            throw AotRefusals::artifactShape($file, 'unknown envelope key ' . array_key_first($unknown));
        }

        foreach (array_keys(self::ENVELOPE) as $required) {
            if (!\array_key_exists($required, $payload)) {
                throw AotRefusals::artifactShape($file, "the '{$required}' key is missing");
            }
        }

        $digest = $payload['digest'];
        if (!\is_string($digest) || $digest === '') {
            throw AotRefusals::artifactShape($file, 'the digest must be a non-empty string');
        }

        return [
            'digest' => $digest,
            'unreferenced' => $payload['unreferenced'],
            'tags' => $payload['tags'],
            'lazy' => $payload['lazy'],
            'entries' => $payload['entries'],
        ];
    }
}
