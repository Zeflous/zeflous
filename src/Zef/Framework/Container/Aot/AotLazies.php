<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use Zef\Framework\Container\ServiceDefinition;

/**
 * The dump side of the artifact's lazy map: the planned definition
 * set's deferral requests, one map entry per lazy declaration -- the
 * identifier the resolution lanes will proxy, and the class its native
 * proxy stands in for.
 *
 * The map bakes for the same reason the tag index bakes: the load lane
 * cannot re-derive it. The compile lane marks its lazy identities from
 * the *planned* set -- post-decoration, where a decorated target's
 * deferral has travelled to its re-homed `@decor:` base identifier --
 * and rebuilding that walk from the parsed declarations would mark the
 * original identifier instead, proxying the outermost wrapper behind
 * the base's class. Baking the planned walk's answer is the one honest
 * spelling ({@see AotArtifact::lazy()}), and the digest -- which folds
 * the declarations' `lazy` keys themselves -- proves the baked map
 * still describes the live configuration.
 */
final readonly class AotLazies
{
    /**
     * Answers the planned definition set's lazy map.
     *
     * @param list<ServiceDefinition> $definitions the pipeline's planned set
     *
     * @return array<string, class-string> the lazy map, keyed by identifier
     *                                     in declaration order
     */
    public static function all(array $definitions): array
    {
        /** @var array<string, class-string> $lazy */
        $lazy = [];

        foreach ($definitions as $definition) {
            if ($definition->lazyClass === null) {
                continue;
            }

            $lazy[$definition->id] = $definition->lazyClass;
        }

        return $lazy;
    }
}
