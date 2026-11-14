<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The contextual-binding lane's synthetic identifier spellings: the
 * `@contextual:` prefix {@see ContextualSet} produces and owns, as the
 * one vocabulary both the producer and the alignment stage read --
 * {@see DecoratorLayers::occupies()} for the decoration lane's own
 * `@decor:` prefix is the exact precedent.
 *
 * The alignment stage's stake in the spelling: a synthetic alias
 * deliberately carries the *consumer's* lifetime (the rewritten chain
 * must behave exactly the way a direct consumer-to-target reference
 * would), a different contract from the ordinary alias's transparency,
 * honoured by skipping the prefix this vocabulary names.
 */
final readonly class ContextualSynthetics
{
    private const string PREFIX = '@contextual:';

    /**
     * Whether the identifier is the contextual lane's own synthetic
     * spelling.
     */
    public static function isSynthetic(string $id): bool
    {
        return str_starts_with($id, self::PREFIX);
    }
}
