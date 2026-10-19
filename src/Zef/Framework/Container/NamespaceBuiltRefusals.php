<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The namespace lane's build-half refusal vocabulary: every fail-closed
 * message the sealed tree's own guards, the scope enforcement walk and
 * the fallback registry's registration can raise (the
 * {@see InterceptionBuiltRefusals} precedent -- the parse half's words
 * live beside the knob that parses them, these beside the machinery
 * that builds).
 *
 * A throwing fallback factory is deliberately absent from this
 * vocabulary: the resolution lane has never wrapped a factory's own
 * failure, and the namespace lane keeps that discipline -- the host's
 * machinery throws with the host's own stack, raw.
 */
final readonly class NamespaceBuiltRefusals
{
    /**
     * The builder was sealed once already -- the boot seals it once.
     */
    public static function alreadySealed(): DefinitionException
    {
        return new DefinitionException(
            'The namespace tree builder is already sealed -- the boot seals it once.',
        );
    }

    /**
     * The tree was sealed -- the boot's insert is too late.
     */
    public static function sealedInsert(): DefinitionException
    {
        return new DefinitionException(
            'The namespace radix tree is sealed -- insert() is not allowed.',
        );
    }

    /**
     * The tree was sealed -- the boot's annotation is too late.
     */
    public static function sealedAnnotate(): DefinitionException
    {
        return new DefinitionException(
            'The namespace radix tree is sealed -- annotate() is not allowed.',
        );
    }

    /**
     * An indexed identifier must be a non-empty string.
     */
    public static function emptyIdentifier(): DefinitionException
    {
        return new DefinitionException(
            'A namespace-indexed service identifier must be a non-empty string.',
        );
    }

    /**
     * An indexed identifier must not spell an empty segment.
     */
    public static function emptySegment(string $id): DefinitionException
    {
        return new DefinitionException(
            "The service identifier '{$id}' spells an empty namespace segment "
            . '(two separators with nothing between them).',
        );
    }

    /**
     * Two identifiers spell one namespace position -- the tree cannot
     * serve both to a prefix enumeration.
     */
    public static function positionCollision(string $existing, string $incoming): DefinitionException
    {
        return new DefinitionException(
            "The service identifiers '{$existing}' and '{$incoming}' spell the same namespace "
            . 'position -- the namespace layer cannot serve both.',
        );
    }

    /**
     * An annotation spells a kind outside the three-kind vocabulary.
     */
    public static function annotateUnknownKind(string $prefix, string $unknown): DefinitionException
    {
        return new DefinitionException(
            "The namespace annotation for '{$prefix}' carries the unknown scope kind "
            . "'{$unknown}' (known kinds: public, internal, module).",
        );
    }

    /**
     * An annotation governs no subtree.
     */
    public static function annotateRootPrefix(string $prefix): DefinitionException
    {
        return new DefinitionException(
            "The namespace annotation prefix '{$prefix}' governs no subtree -- a bare "
            . 'separator annotates nothing.',
        );
    }

    /**
     * One prefix, two kinds -- the annotations disagree.
     */
    public static function annotateKindDisagreement(
        string $collapsed,
        string $known,
        string $incoming,
    ): DefinitionException {
        return new DefinitionException(
            "The namespace '{$collapsed}' is annotated both '{$known}' and '{$incoming}' "
            . '-- one subtree, one annotation.',
        );
    }

    /**
     * An internal-scoped service was referenced from outside its
     * subtree.
     */
    public static function internalViolation(string $consumer, string $dep, string $prefix): DefinitionException
    {
        return new DefinitionException(
            "Namespace scope violation: service '{$consumer}' references internal service "
            . "'{$dep}' from outside the guarded namespace '{$prefix}'.",
        );
    }

    /**
     * A module-scoped service was referenced while the cross-scope
     * budget denies every outbound reference.
     */
    public static function moduleBudgetZero(string $consumer, string $dep): DefinitionException
    {
        return new DefinitionException(
            "Namespace scope violation: service '{$consumer}' references module-scoped service "
            . "'{$dep}' while max_cross_scope_refs is 0.",
        );
    }

    /**
     * A (consumer => target) pair spent its whole cross-scope budget of
     * distinct targets.
     */
    public static function moduleBudgetExceeded(string $pair, int $budget): DefinitionException
    {
        return new DefinitionException(
            "Namespace scope violation: cross-scope references into '{$pair}' exceed "
            . 'the limit (' . $budget . ').',
        );
    }

    /**
     * A fallback entry cannot ride the request scope.
     */
    public static function fallbackRequestLifetime(string $prefix): DefinitionException
    {
        return new DefinitionException(
            "The namespace fallback for '{$prefix}' cannot be request-scoped -- fallback "
            . 'identifiers are not scope members.',
        );
    }

    /**
     * The declared fallback prefixes exceed the lane's hard bound.
     */
    public static function fallbackBudgetExceeded(int $budget): DefinitionException
    {
        return new DefinitionException(
            "The namespace fallback registry declares more than {$budget} prefixes "
            . "-- the lane's hard bound.",
        );
    }

    /**
     * A fallback prefix must govern a real namespace.
     */
    public static function fallbackRootPrefix(string $prefix): DefinitionException
    {
        return new DefinitionException(
            "The namespace fallback prefix '{$prefix}' governs no namespace -- a bare "
            . 'separator would rebind every miss, not a namespace of them.',
        );
    }
}
