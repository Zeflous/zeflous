<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The validated load side of the artifact: the payload one
 * {@see ContainerAotCache::load()} answered, every structural promise
 * checked before a single entry installs.
 *
 * The artifact is self-generated -- the dump wrote it from a validated
 * planned set -- but it crosses a file boundary, and a torn write, a
 * truncated copy or a hand edit must refuse rather than half-boot. So
 * the validation is the strict mirror of the dump's own shape: the
 * envelope's five keys exactly ({@see AotRefusals::artifactShape()}),
 * the digest a non-empty string, the unreferenced report a list of
 * identifiers, the tag index a map of identifier lists, the lazy map a
 * map of instantiable class names ({@see AotArtifactLazies} -- the
 * lazy map's own unit, the same split the identifier lists and the
 * per-definition walk take), and the entries each their typed form
 * ({@see AotArtifactEntries} -- the per-definition walk's own unit,
 * the same split the definition grammar's map and entry validation
 * use).
 *
 * What the validation answers is typed, not re-checked: the raw
 * payload's entries become {@see AotClosureEntry} and
 * {@see AotBuildEntry} value objects -- the same move the definition
 * grammar makes turning raw declaration maps into typed
 * {@see \Zef\Framework\Container\ServiceDefinition} objects -- so the
 * install half reads proof, not promises. The graph the dump validated
 * is not re-walked at load: the artifact earned its validity at dump
 * time, and the container's own runtime
 * {@see \Zef\Framework\Container\CycleGuard} remains the seatbelt for
 * anything that would smuggle a cycle past the boundary anyway.
 */
final readonly class AotArtifact
{
    /**
     * @param list<string>                                 $unreferenced the report baked at dump time
     * @param array<string, list<string>>                  $tags         the baked tag index
     * @param array<string, class-string>                  $lazy         the baked lazy map
     * @param array<string, AotBuildEntry|AotClosureEntry> $entries      the validated entries
     */
    private function __construct(
        private string $digest,
        private array $unreferenced,
        private array $tags,
        private array $lazy,
        private array $entries,
    ) {
    }

    /**
     * The digest the dump baked: the configuration fingerprint the boot
     * compares against its own.
     */
    public function digest(): string
    {
        return $this->digest;
    }

    /**
     * The unreferenced report baked at dump time, in declaration order.
     *
     * @return list<string>
     */
    public function unreferenced(): array
    {
        return $this->unreferenced;
    }

    /**
     * The baked tag index: which identifiers carry which tag, the map the
     * tags locator's read side answers from on the load lane.
     *
     * @return array<string, list<string>>
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * The baked lazy map: which identifiers defer behind which proxy
     * class, the marks the load lane writes into the container's lazy
     * registry after its install loop ({@see AotBootLane} -- the
     * compile lane's own marking step, baked the same way the tag index
     * bakes, because rebuilding it from the parsed declarations would
     * not survive the decoration lane's re-homing).
     *
     * @return array<string, class-string>
     */
    public function lazy(): array
    {
        return $this->lazy;
    }

    /**
     * The validated entries, keyed by identifier in declaration order.
     *
     * @return array<string, AotBuildEntry|AotClosureEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * Validates a loaded payload into an artifact, refusing every
     * structural defect with the file named: the envelope through its
     * own unit, each member through the validator that owns its shape.
     *
     * @param mixed $payload whatever the artifact file returned
     */
    public static function validated(string $file, mixed $payload): self
    {
        $envelope = AotArtifactEnvelope::validated($file, $payload);

        return new self(
            $envelope['digest'],
            AotArtifactIds::validated($file, 'the unreferenced report', $envelope['unreferenced']),
            self::validatedTags($file, $envelope['tags']),
            AotArtifactLazies::validated($file, $envelope['lazy']),
            AotArtifactEntries::validated($file, $envelope['entries']),
        );
    }

    /**
     * @param mixed $tags the payload's `tags` member
     *
     * @return array<string, list<string>>
     */
    private static function validatedTags(string $file, mixed $tags): array
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
