<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One target's declared decorator chain: the positional list of
 * {@see DecoratorEntry} maps, parsed in declaration order -- the first
 * entry the outermost wrapper, the reference container's own chain
 * semantics kept whole.
 */
final readonly class DecoratorEntries
{
    /**
     * Parses the target's raw chain value into its validated entries.
     *
     * @return list<DecoratorEntry>
     */
    public static function parse(string $target, mixed $chain): array
    {
        if (!\is_array($chain) || !array_is_list($chain)) {
            throw DecoratorRefusals::chainNotAList($target, $chain);
        }

        if ($chain === []) {
            throw DecoratorRefusals::emptyChain($target);
        }

        return array_map(
            static fn (mixed $entry): DecoratorEntry => DecoratorEntryParser::parsed($target, $entry),
            $chain,
        );
    }
}
