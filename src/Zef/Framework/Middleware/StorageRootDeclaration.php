<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;

/**
 * The storage map's declaration fold: the strict read of the one
 * `storage` knob block -- `root` -- answering the root the host
 * declared, or null when the map stays silent (an absent block, an
 * empty map, or the empty string all declare no layout).
 *
 * The census is strict because a silent typo is the worst failure
 * shape available: a `storage.rooot` misspelling that fell through to
 * the ad-hoc posture would leave a deployment believing it declared
 * the map while every derivation quietly answers nothing. A map that
 * is not a map, a key that is not `root`, or a `root` that is not a
 * string each refuse with their own name and the known list spelled
 * ({@see ErrorStorageWiring}, the family fold this declaration feeds
 * -- the wiring walks the declared root, this fold only reads it).
 *
 * The fold exists because the wiring's own first spelling carried the
 * census and the walk in one class and answered the architecture
 * gate's difficulty ceiling at 26.50 against the house limit of 20;
 * the split leaves the declaration's strict read alone here, the same
 * fold-split discipline the storage root's own geometry
 * (`StorageDocumentRoot`) and the event lane's bookkeeping
 * (`EventBook`) already answer with. It reads the frozen
 * configuration and nothing else -- the walk, the lane addresses and
 * the precedence all stay the wiring's own questions.
 */
final class StorageRootDeclaration
{
    /**
     * The root the host declared, or null when the storage map stays
     * silent: no block, an empty map, or the empty-string root all
     * answer the no-layout posture the families keep when nothing is
     * declared.
     *
     * @throws ConfigException when the storage map is malformed or
     *                         carries an unknown knob
     */
    public static function root(Config $config): ?string
    {
        $storage = $config->get('storage');
        if ($storage === null) {
            return null;
        }

        if (!\is_array($storage)) {
            throw new ConfigException(
                'The storage configuration must be a map, ' . get_debug_type($storage) . ' given.',
            );
        }

        foreach (array_keys($storage) as $knob) {
            if (\is_string($knob) && $knob === 'root') {
                continue;
            }

            throw new ConfigException(
                "The storage configuration carries the unknown knob '"
                . (\is_string($knob) ? $knob : get_debug_type($knob))
                . "' (known knobs: root).",
            );
        }

        $root = $storage['root'] ?? '';
        if (!\is_string($root)) {
            throw new ConfigException('storage.root must be a string.');
        }

        return $root === '' ? null : $root;
    }
}
