<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * The events family's declaration fold: the strict read of the one
 * `events.deferred` knob block the wired default reads -- the three
 * knobs the store's composition consumes (`store`, `path`,
 * `capacity`) -- answered as one immutable value object the wiring
 * composes over.
 *
 * The family census (which children the `events` map may carry) is
 * the family tree's own fold ({@see EventFamilies}), the store
 * word's closed vocabulary is its own ({@see EventDeliveryStores})
 * and the capacity's integer seam is its own ({@see
 * EventDeliveryCapacity}) -- each reached one-way exactly as the
 * HTTP families' readers reach theirs, so this fold owns only the
 * family's own grammar: every knob read strictly, the unknown
 * knob and the foreign type each a named halt with the known list
 * spelled, because a silent typo is the worst failure shape
 * available ({@see Middleware\StorageRootDeclaration} and
 * {@see Http\StrictKnobs} lineages): an `events.deffered`
 * misspelling that fell through to the silent posture would leave a
 * deployment believing it asked for the wired default while the
 * service resolution keeps refusing.
 *
 * The capacity knob reads through its own integer seam ({@see
 * EventDeliveryCapacity}) rather than a bare `is_int`: the
 * environment overlay writes its values as the raw strings the
 * environment carries (`EnvSource` passing them through verbatim),
 * so `APP_EVENTS__DEFERRED__CAPACITY=2048` must fold to the integer
 * it spells.
 *
 * The silent family is an ANSWER, not a failure: an absent
 * `events` block, an absent `deferred` map, or the empty `store`
 * word each declare no wired default -- the arm stays opt-in
 * exactly as {@see Event\EventDispatcher::durableDelivery()}
 * spells it structurally, and it is the wiring's own question
 * (never this fold's) what resolving the wired default's service
 * identity without declaring one should answer.
 */
final readonly class EventDeliveryDeclaration
{
    /**
     * The pending bound's default: the admission ceiling a
     * deployment keeps when it spells neither its own number nor
     * an environment overlay -- the same order of magnitude the
     * in-process queue's own callers spell, declared here so the
     * framework profile and the shipped file cannot disagree.
     */
    public const int DEFAULT_CAPACITY = 1024;

    /**
     * @param string $store    the store word ('' declares no wired
     *                         default, 'file' the filesystem answer)
     * @param string $path     the explicitly declared directory ('' the
     *                         derive posture over the `queue` lane)
     * @param int    $capacity the store's pending bound (>= 1)
     */
    public function __construct(
        public string $store,
        public string $path,
        public int $capacity,
    ) {
    }

    /**
     * Reads the declaration from the frozen repository's `events`
     * subtree: the family tree answers the `deferred` family's map
     * (absent blocks answer the silent posture -- no wired default,
     * the default capacity), and the family's own grammar reads it.
     *
     * @throws ConfigException when the events map is malformed or
     *                         carries an unknown family, the deferred map is likewise
     *                         malformed or carries an unknown knob, the store word is
     *                         not a string inside the closed vocabulary, the path is
     *                         not a string, or the capacity is not an integer or
     *                         all-digit string of at least one
     */
    public static function fromConfig(Config $config): self
    {
        return self::fromMap(
            EventFamilies::subtree($config->get('events'), 'deferred'),
        );
    }

    /**
     * Whether the declaration asks for the wired default at all:
     * the non-empty store word. The wiring reads this to decide
     * between composing the file answer and refusing with the way
     * out spelled -- a question the declaration itself never owns.
     */
    public function wantsWiredStore(): bool
    {
        return $this->store !== '';
    }

    /**
     * Reads the declaration from the `deferred` family's own map.
     *
     * @param mixed $deferred the family's map as the frozen repository
     *                        carries it: a knob map, or null when the family is
     *                        absent
     *
     * @throws ConfigException when the map is malformed or carries
     *                         an unknown knob, or any knob's value refuses
     */
    private static function fromMap(mixed $deferred): self
    {
        if ($deferred === null) {
            return new self('', '', self::DEFAULT_CAPACITY);
        }

        if (!\is_array($deferred)) {
            throw new ConfigException(
                'The events.deferred configuration must be a map, ' . get_debug_type($deferred) . ' given.',
            );
        }

        EventDeliveryKnobs::rejectUnknown($deferred);

        return new self(
            EventDeliveryStores::read($deferred['store'] ?? ''),
            self::readPath($deferred['path'] ?? ''),
            EventDeliveryCapacity::read($deferred['capacity'] ?? self::DEFAULT_CAPACITY),
        );
    }

    /**
     * The path knob's own strict reading: any address string is
     * the deployment's own word (the empty string the derive
     * posture), and every other type halts with the knob named.
     *
     * @throws ConfigException when the value is not a string
     */
    private static function readPath(mixed $path): string
    {
        if (!\is_string($path)) {
            throw new ConfigException('events.deferred.path must be a string.');
        }

        return $path;
    }
}
