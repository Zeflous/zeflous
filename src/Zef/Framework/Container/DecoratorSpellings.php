<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decoration lane's collision vocabulary: the synthetic identifiers
 * one target's chain owns -- the re-homed base and every between-layer
 * wrapper (k = 1..count-1) -- the spellings a declared identifier may
 * not collide with when the surgery runs ({@see DecoratorSet}'s guard
 * walks them; the {@see ContextualSet} precedent of one unit owning the
 * collision grammar it checks against).
 */
final readonly class DecoratorSpellings
{
    /**
     * The identifiers this one chain's surgery owns.
     *
     * @param int $count the chain's length
     *
     * @return list<string>
     */
    public static function for(string $target, int $count): array
    {
        $spelled = [DecoratorLayers::base($target)];
        for ($k = 1; $k < $count; ++$k) {
            $spelled[] = DecoratorLayers::layer($target, $k);
        }

        return $spelled;
    }
}
