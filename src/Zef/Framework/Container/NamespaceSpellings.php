<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The namespace layer's spelling vocabulary: the one place that owns how
 * a service identifier segments into a namespace path, so the radix tree
 * ({@see NamespaceRadixTree}), the scope policy ({@see NamespacesMap}),
 * the enforcement walk ({@see NamespaceScopes}) and the fallback
 * registry ({@see NamespaceFallbacks}) can never disagree about what a
 * prefix means (the {@see DecoratorSpellings} precedent -- the lane's
 * vocabulary stays a word-owning unit, the lane's logic stays
 * logic-only).
 *
 * Two separators spell one boundary: the dot the framework's own
 * identifiers use (`framework.config`) and the backslash a class-name
 * identifier uses (`App\Widget\Controller`). Every segmentation first
 * collapses the backslash onto the dot, so both spellings walk the same
 * tree -- `App\Internal\Thing` and `App.Internal.Thing` address the same
 * namespace position -- and every comparison the layer makes (scope-of,
 * fallback-for, prefix descent) runs on the collapsed form, never on
 * the spelling the host happened to write.
 *
 * The collapsed form never carries an empty segment: an identifier that
 * spells one (`a..b`) is the caller's refusal to make, not a segment
 * the tree would silently carry.
 */
final readonly class NamespaceSpellings
{
    /** The segment separator the collapsed form spells. */
    public const string SEPARATOR = '.';

    /**
     * The identifier's namespace segments: the backslash collapsed onto
     * the dot first, then split.
     *
     * @return list<string> the segments, in path order
     */
    public static function segments(string $id): array
    {
        return explode(self::SEPARATOR, self::collapsed($id));
    }

    /**
     * The identifier's collapsed form: the one spelling every
     * starts-with comparison and every prefix key uses.
     */
    public static function collapsed(string $id): string
    {
        return str_replace('\\', self::SEPARATOR, $id);
    }

    /**
     * The prefix's collapsed, trailing-separator form -- the one key the
     * annotations map and the fallback registry store, and the form
     * every starts-with comparison reads. The trailing separator is
     * what makes segment-boundary semantics honest: `framework` never
     * matches `frameworkX`, only `framework.*`.
     *
     * The root-only spelling (a bare separator, or the empty string)
     * collapses to the empty string -- the answer every caller refuses
     * on its own terms, because a prefix that matches everything
     * governs nothing.
     */
    public static function prefix(string $prefix): string
    {
        $trimmed = mb_rtrim(self::collapsed($prefix), self::SEPARATOR);

        return $trimmed === '' ? '' : $trimmed . self::SEPARATOR;
    }

    /**
     * Whether a collapsed prefix GOVERNS a collapsed identifier: the
     * subtree rooted at the prefix includes the root itself, and every
     * descendant beneath it -- the one relation the scope annotation
     * walk, the enforcement's same-subtree check and the fallback
     * coverage all read, so the three can never disagree about what a
     * prefix owns.
     *
     * Both arguments must be collapsed spellings, and the prefix must
     * carry its trailing separator (the form {@see prefix()} answers)
     * -- the precondition every caller's own grammar already enforces,
     * because a prefix that reaches this seam empty would govern
     * everything, and the layers above refuse exactly that.
     */
    public static function governs(string $collapsedPrefix, string $collapsedId): bool
    {
        return $collapsedId === mb_rtrim($collapsedPrefix, self::SEPARATOR)
            || str_starts_with($collapsedId, $collapsedPrefix);
    }

    /**
     * The identifier's own governing-prefix chain: every segment prefix
     * of the collapsed identifier, shortest first, each in the
     * trailing-separator form the annotations map and the fallback
     * registry key -- exactly the prefixes {@see governs()} would
     * answer true for, in the one order a walk can ride (the longest
     * governing entry is the last one the chain finds registered).
     *
     * The argument must be a collapsed spelling; an empty one answers
     * the empty chain.
     *
     * @return list<string> the governing prefixes, shortest first
     */
    public static function prefixesOf(string $collapsedId): array
    {
        $prefixes = [];
        $accumulated = '';

        foreach (explode(self::SEPARATOR, $collapsedId) as $segment) {
            $accumulated .= $segment . self::SEPARATOR;
            $prefixes[] = $accumulated;
        }

        return $prefixes;
    }
}
