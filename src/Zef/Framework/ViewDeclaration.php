<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * The view family's declaration fold: the strict read of the one
 * `views` knob block the wired default reads -- the two knobs the
 * engine's composition consumes (`engine`, `path`) -- answered as
 * one immutable value object the wiring composes over.
 *
 * The knob census is the family's own ({@see ViewKnobs}), the engine
 * word's closed vocabulary is its own ({@see ViewEngines}) -- each
 * reached one-way exactly as the events and uploads families'
 * readers reach theirs, so this fold owns only the family's own
 * grammar: every knob read strictly, the unknown knob and the
 * foreign type each a named halt with the known list spelled,
 * because a silent typo is the worst failure shape available
 * ({@see Middleware\StorageRootDeclaration} and {@see
 * Http\StrictKnobs} lineages): a `veiws` misspelling at the
 * repository's own key that fell through to the silent posture
 * would leave a deployment believing it asked for the wired engine
 * while the service resolution keeps refusing.
 *
 * The silent family is an ANSWER, not a failure: an absent `views`
 * block, or the empty `engine` word, each declare no wired default
 * -- the presentation port stays opt-in exactly as the unwired
 * lane's own slice carried it, and it is the wiring's own question
 * (never this fold's) what resolving the wired default's service
 * identity without declaring one should answer.
 */
final readonly class ViewDeclaration
{
    /**
     * @param string $engine the engine word ('' declares no wired
     *                       default, 'php' the native answer, 'twig'
     *                       the Twig answer over the host tree's own
     *                       Twig)
     * @param string $path   the templates root the word composes over (''
     *                       the address-less posture the wiring names a halt)
     */
    public function __construct(
        public string $engine,
        public string $path,
    ) {
    }

    /**
     * Reads the declaration from the frozen repository's `views`
     * subtree: an absent block answers the silent posture -- no
     * wired default, the empty path -- and the family's own grammar
     * reads it.
     *
     * @throws ConfigException when the views map is malformed,
     *                         carries an unknown knob, or any knob's value refuses
     */
    public static function fromConfig(Config $config): self
    {
        return self::fromMap($config->get('views'));
    }

    /**
     * Whether the declaration asks for the wired default at all:
     * the non-empty engine word. The wiring reads this to decide
     * between composing the native answer and refusing with the way
     * out spelled -- a question the declaration itself never owns.
     */
    public function wantsWiredEngine(): bool
    {
        return $this->engine !== '';
    }

    /**
     * Reads the declaration from the family's own map.
     *
     * @param mixed $views the `views` map as the frozen repository
     *                     carries it: a knob map, or null when the family is
     *                     absent
     *
     * @throws ConfigException when the map is malformed or carries
     *                         an unknown knob, or any knob's value refuses
     */
    private static function fromMap(mixed $views): self
    {
        if ($views === null) {
            return new self('', '');
        }

        if (!\is_array($views)) {
            throw new ConfigException(
                'The views configuration must be a map, ' . get_debug_type($views) . ' given.',
            );
        }

        ViewKnobs::rejectUnknown($views);

        return new self(
            ViewEngines::read($views['engine'] ?? ''),
            self::readPath($views['path'] ?? ''),
        );
    }

    /**
     * The path knob's own strict reading: any address string is the
     * deployment's own word (the empty string the address-less
     * posture the wiring names a halt), and every other type halts
     * with the knob named.
     *
     * @throws ConfigException when the value is not a string
     */
    private static function readPath(mixed $path): string
    {
        if (!\is_string($path)) {
            throw new ConfigException('views.path must be a string.');
        }

        return $path;
    }
}
