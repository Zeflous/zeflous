<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * The temp family's declaration fold: the strict read of the one
 * `temp` knob block the lane's writer consumes -- the two knobs
 * the composition spells (`path`, `ttl`) -- answered as one
 * immutable value object the wiring composes over.
 *
 * The knob census is the family's own ({@see TempKnobs}), the
 * retention's integer seam is its own ({@see TempRetention}) --
 * each reached one-way exactly as the uploads family's readers
 * reach theirs, so this fold owns only the family's own grammar:
 * every knob read strictly, the unknown knob and the foreign type
 * each a named halt with the known list spelled, because a
 * silent typo is the worst failure shape available ({@see
 * UploadsDeclaration} the lineage): a `temp.tll` misspelling at
 * the map's own key that fell through to the silent posture would
 * leave a deployment believing it asked for the writer while the
 * service resolution keeps refusing.
 *
 * The silent family is an ANSWER, not a failure: an absent
 * `temp` block, or the null `ttl` knob, each declare no armed
 * writer -- the fail-closed posture the uploads lane's own DoD
 * spells (a knob the host never spelled is a surface that stays
 * shut), and it is the wiring's own question (never this fold's)
 * what resolving the writer's service identity without declaring
 * one should answer -- the null retention itself, the one reading
 * the wiring narrows its refusal over. The TTL carries no
 * default the way the uploads ceiling does: the retention IS the
 * opt-in word, the allowlist's own posture replayed -- a host
 * that spells its block without it is a host the family answers
 * as silent.
 */
final readonly class TempDeclaration
{
    /**
     * @param string   $path       the explicitly declared directory ('' the
     *                             derive posture over the `temp` lane)
     * @param null|int $ttlSeconds the retention, in seconds (null: the
     *                             silent posture, no armed writer)
     */
    public function __construct(
        public string $path,
        public ?int $ttlSeconds,
    ) {
    }

    /**
     * Reads the declaration from the frozen repository's `temp`
     * subtree: an absent block answers the silent posture -- no
     * armed writer, the null retention -- and the family's own
     * grammar reads it.
     *
     * @throws ConfigException when the temp map is malformed,
     *                         carries an unknown knob, or any knob's value refuses
     */
    public static function fromConfig(Config $config): self
    {
        return self::fromMap($config->get('temp'));
    }

    /**
     * Whether the declaration arms the writer at all: a declared
     * retention. The wiring's own refusal narrows the null retention
     * itself (the one reading the composition spells its halt
     * over), and the suite pins the posture through this fold --
     * the vocabulary the uploads family's own {@see
     * UploadsDeclaration::wantsWriter()} planted, replayed as the
     * mortality this lane spells.
     */
    public function wantsWriter(): bool
    {
        return $this->ttlSeconds !== null;
    }

    /**
     * Reads the declaration from the family's own map.
     *
     * @param mixed $temp the `temp` map as the frozen repository
     *                   carries it: a knob map, or null when the family is
     *                   absent
     *
     * @throws ConfigException when the map is malformed or carries
     *                         an unknown knob, or any knob's value refuses
     */
    private static function fromMap(mixed $temp): self
    {
        if ($temp === null) {
            return new self('', null);
        }

        if (!\is_array($temp)) {
            throw new ConfigException(
                'The temp configuration must be a map, ' . get_debug_type($temp) . ' given.',
            );
        }

        TempKnobs::rejectUnknown($temp);

        $ttl = $temp['ttl'] ?? null;

        return new self(
            self::readPath($temp['path'] ?? ''),
            $ttl === null ? null : TempRetention::read($ttl),
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
            throw new ConfigException('temp.path must be a string.');
        }

        return $path;
    }
}
