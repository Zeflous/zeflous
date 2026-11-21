<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * The uploads family's declaration fold: the strict read of the one
 * `uploads` knob block the lane's first writer consumes -- the
 * three knobs the composition spells (`path`, `max_bytes`,
 * `types`) -- answered as one immutable value object the wiring
 * composes over.
 *
 * The knob census is the family's own ({@see UploadsKnobs}), the
 * ceiling's integer seam is its own ({@see UploadsBudget}) and the
 * allowlist's grammar is its own ({@see UploadsTypes}) -- each
 * reached one-way exactly as the events family's readers reach
 * theirs, so this fold owns only the family's own grammar: every
 * knob read strictly, the unknown knob and the foreign type each a
 * named halt with the known list spelled, because a silent typo is
 * the worst failure shape available ({@see
 * Middleware\StorageRootDeclaration} and {@see Http\StrictKnobs}
 * lineages): an `upload.max_bytes` misspelling at the map's own key
 * that fell through to the silent posture would leave a deployment
 * believing it asked for the writer while the service resolution
 * keeps refusing.
 *
 * The silent family is an ANSWER, not a failure: an absent
 * `uploads` block, or the empty `types` list, each declare no
 * armed writer -- the fail-closed posture the lane's own DoD
 * spells (a knob the host never spelled is a surface that stays
 * shut), and it is the wiring's own question (never this fold's)
 * what resolving the writer's service identity without declaring
 * one should answer.
 */
final readonly class UploadsDeclaration
{
    /**
     * The silent family's ceiling default: the same order of
     * magnitude the engine's own INI ceiling ships
     * (`upload_max_filesize`'s two megabytes), declared here so the
     * framework profile and the shipped file cannot disagree. A
     * deployment that spells its types but not its ceiling keeps
     * this bound, never an unbounded writer.
     */
    public const int DEFAULT_MAX_BYTES = 2_097_152;

    /**
     * @param string                 $path     the explicitly declared directory ('' the
     *                                         derive posture over the `uploads` lane)
     * @param int                    $maxBytes the writer's size ceiling (>= 1)
     * @param list<non-empty-string> $types    the media types the writer accepts
     *                                         (empty: the silent posture, no armed writer)
     */
    public function __construct(
        public string $path,
        public int $maxBytes,
        public array $types,
    ) {
    }

    /**
     * Reads the declaration from the frozen repository's `uploads`
     * subtree: an absent block answers the silent posture -- no
     * armed writer, the default ceiling, the empty allowlist -- and
     * the family's own grammar reads it.
     *
     * @throws ConfigException when the uploads map is malformed,
     *                         carries an unknown knob, or any knob's value refuses
     */
    public static function fromConfig(Config $config): self
    {
        return self::fromMap($config->get('uploads'));
    }

    /**
     * Whether the declaration arms the writer at all: a non-empty
     * allowlist. The wiring reads this to decide between composing
     * the file answer and refusing with the way out spelled -- a
     * question the declaration itself never owns.
     */
    public function wantsWriter(): bool
    {
        return $this->types !== [];
    }

    /**
     * Reads the declaration from the family's own map.
     *
     * @param mixed $uploads the `uploads` map as the frozen repository
     *                       carries it: a knob map, or null when the family is
     *                       absent
     *
     * @throws ConfigException when the map is malformed or carries
     *                         an unknown knob, or any knob's value refuses
     */
    private static function fromMap(mixed $uploads): self
    {
        if ($uploads === null) {
            return new self('', self::DEFAULT_MAX_BYTES, []);
        }

        if (!\is_array($uploads)) {
            throw new ConfigException(
                'The uploads configuration must be a map, ' . get_debug_type($uploads) . ' given.',
            );
        }

        UploadsKnobs::rejectUnknown($uploads);

        return new self(
            self::readPath($uploads['path'] ?? ''),
            UploadsBudget::read($uploads['max_bytes'] ?? self::DEFAULT_MAX_BYTES),
            UploadsTypes::read($uploads['types'] ?? null),
        );
    }

    /**
     * The path knob's own strict reading: any address string is the
     * deployment's own word (the empty string the derive posture),
     * and every other type halts with the knob named.
     *
     * @throws ConfigException when the value is not a string
     */
    private static function readPath(mixed $path): string
    {
        if (!\is_string($path)) {
            throw new ConfigException('uploads.path must be a string.');
        }

        return $path;
    }
}
