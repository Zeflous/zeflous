<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The contextual-binding lane's set surgery: a planned definition set
 * rewritten with one binding applied -- the consumer's effective
 * declaration rewritten in place, the binding's synthetic alias
 * appended, and a definition already holding the synthetic identifier
 * refused.
 */
final readonly class ContextualSet
{
    /**
     * Answers the set with the binding applied.
     *
     * @param list<ServiceDefinition> $definitions
     *
     * @return list<ServiceDefinition>
     */
    public static function withBinding(
        array $definitions,
        int $index,
        ServiceDefinition $serviceDefinition,
        string $dep,
        string $target,
    ): array {
        $via = '@contextual:' . $serviceDefinition->id . '|' . $dep;

        $rewritten = [];
        foreach ($definitions as $position => $entry) {
            if ($entry->id === $via) {
                throw ContextualRefusals::collision($via);
            }

            $rewritten[] = $position === $index
                ? ContextualRewrites::rewritten($serviceDefinition, $dep, $via)
                : $entry;
        }

        $rewritten[] = new AliasDefinition($via, $target, $serviceDefinition->lifetime);

        return $rewritten;
    }
}
