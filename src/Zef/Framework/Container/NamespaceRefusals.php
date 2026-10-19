<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The namespace lane's parse-half refusal vocabulary: every fail-closed
 * message the `namespaces` knob's parse can raise, in one word-owning
 * unit (the {@see InterceptionRefusals} precedent -- the lane stays
 * logic-only, the words stay testable). The build half's words --
 * the tree's own refusals, the scope violations, the fallback
 * registry's -- live in their own unit
 * ({@see NamespaceBuiltRefusals}), the split the lane's own
 * parse-versus-build boundary draws.
 */
final readonly class NamespaceRefusals
{
    /** The type-given tail the shape refusals share (one spelling). */
    private const string GIVEN = ' given.';

    /**
     * The namespaces knob must be a map of its two keys.
     */
    public static function notAMap(string $key, mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration must be a map of the namespace lane's keys "
            . '(scopes, max_cross_scope_refs), ' . get_debug_type($source) . self::GIVEN,
        );
    }

    /**
     * The namespaces knob carries a key outside its two-key vocabulary.
     */
    public static function unknownKey(string $key, string $unknown): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration carries the unknown key '{$unknown}' "
            . '(known keys: scopes, max_cross_scope_refs).',
        );
    }

    /**
     * The scopes member must be a map of prefix => kind.
     */
    public static function scopesNotAMap(string $key, mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.scopes' configuration must be a map of namespace prefixes to scope kinds, "
            . get_debug_type($source) . self::GIVEN,
        );
    }

    /**
     * A scope annotation spells a kind outside the three-kind vocabulary.
     */
    public static function unknownScopeKind(string $key, string $prefix, string $unknown): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.scopes' entry for '{$prefix}' carries the unknown scope kind '{$unknown}' "
            . '(known kinds: public, internal, module).',
        );
    }

    /**
     * A scope annotation's prefix must be a non-empty string.
     */
    public static function prefixNotANonEmptyString(string $key, mixed $prefix): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.scopes' keys must be non-empty namespace prefixes, "
            . get_debug_type($prefix) . self::GIVEN,
        );
    }

    /**
     * A scope annotation's prefix must spell at least one real segment.
     */
    public static function rootPrefix(string $key, string $prefix): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.scopes' prefix '{$prefix}' annotates nothing -- a bare separator "
            . 'governs no subtree; spell the namespace the scope is for.',
        );
    }

    /**
     * A scope annotation's prefix must not spell an empty segment.
     */
    public static function emptySegment(string $key, string $prefix): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.scopes' prefix '{$prefix}' spells an empty namespace segment "
            . '(two separators with nothing between them).',
        );
    }

    /**
     * Two spellings of one namespace position disagree -- one prefix,
     * two scope entries.
     */
    public static function scopeReused(
        string $key,
        string $collapsed,
        string $first,
        string $second,
    ): DefinitionException {
        return new DefinitionException(
            "The '{$key}.scopes' prefixes '{$first}' and '{$second}' both spell the namespace "
            . "'{$collapsed}' -- one subtree, one annotation.",
        );
    }

    /**
     * The declared scope annotations exceed the lane's hard bound.
     */
    public static function scopeBudgetExceeded(string $key, int $budget): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.scopes' configuration declares more than {$budget} annotations "
            . "-- the lane's hard bound.",
        );
    }

    /**
     * The cross-scope budget must be an integer.
     */
    public static function maxRefsNotAnInteger(string $key, mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.max_cross_scope_refs' configuration must be an integer, "
            . get_debug_type($source) . self::GIVEN,
        );
    }

    /**
     * The cross-scope budget must not be negative.
     */
    public static function maxRefsNegative(string $key, int $source): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.max_cross_scope_refs' configuration must be zero or more, "
            . $source . self::GIVEN,
        );
    }
}
