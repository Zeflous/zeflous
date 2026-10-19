<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The scope kind's own vocabulary: the three-kind check one
 * annotation's value owes -- split from {@see NamespaceScopeParser}'s
 * loop as its own unit so the parser keeps the loop and the kind
 * grammar keeps its refusal (one concern, one unit: the prefix's
 * grammar lives beside it in {@see NamespacePrefixGrammar}).
 */
final readonly class NamespaceScopeKinds
{
    public const string SCOPE_PUBLIC = 'public';

    public const string SCOPE_INTERNAL = 'internal';

    public const string SCOPE_MODULE = 'module';

    public const array SCOPES = [
        self::SCOPE_PUBLIC,
        self::SCOPE_INTERNAL,
        self::SCOPE_MODULE,
    ];

    /**
     * Asserts the annotation's kind against the three-kind vocabulary,
     * refusing everything else with the kind's own spelling or type,
     * and answering the kind it validated.
     *
     * @param string $key    the knob's own key, for the refusal
     * @param mixed  $prefix the raw annotation key, for the refusal
     * @param mixed  $kind   the raw annotation value
     */
    public function asserted(string $key, mixed $prefix, mixed $kind): string
    {
        if (!\is_string($kind) || !\in_array($kind, self::SCOPES, true)) {
            throw NamespaceRefusals::unknownScopeKind(
                $key,
                \is_string($prefix) ? $prefix : get_debug_type($prefix),
                \is_string($kind) ? $kind : get_debug_type($kind),
            );
        }

        return $kind;
    }
}
