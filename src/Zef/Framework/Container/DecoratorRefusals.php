<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The decoration lane's refusal vocabulary: every fail-closed message the
 * knob's parse and the boot apply can raise, in one class of word-owning
 * units (the {@see ContextualRefusals} precedent -- the lane stays
 * logic-only, the words stay testable).
 */
final readonly class DecoratorRefusals
{
    /** The type-given tail the shape refusals share (the S1192 line: one spelling). */
    private const string GIVEN = ' given.';

    /**
     * The knob itself must be a map of targets to decorator lists.
     */
    public static function notAMap(string $key, mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration must be a map of service identifiers to decorator lists, "
            . get_debug_type($source) . self::GIVEN,
        );
    }

    /**
     * A target key must be a non-empty service identifier.
     */
    public static function targetNotAnIdentifier(mixed $target): DefinitionException
    {
        return new DefinitionException(
            'A decorator target must be a non-empty service identifier, '
            . get_debug_type($target) . self::GIVEN,
        );
    }

    /**
     * A declared chain must be a positional list.
     */
    public static function chainNotAList(string $target, mixed $chain): DefinitionException
    {
        return new DefinitionException(
            "The decorator list for '{$target}' must be a positional list, "
            . get_debug_type($chain) . self::GIVEN,
        );
    }

    /**
     * A declared chain must carry at least one decorator.
     */
    public static function emptyChain(string $target): DefinitionException
    {
        return new DefinitionException(
            "The decorator list for '{$target}' is empty -- declare at least one decorator, "
            . "or drop the '{$target}' entry from the 'decorators' map.",
        );
    }

    /**
     * Each decorator entry must itself be a map.
     */
    public static function entryNotAMap(string $target, mixed $entry): DefinitionException
    {
        return new DefinitionException(
            "Each decorator for '{$target}' must be a map, "
            . get_debug_type($entry) . self::GIVEN,
        );
    }

    /**
     * The decorator entry grammar accepts exactly three keys.
     */
    public static function unknownEntryKey(string $target, string $unknown): DefinitionException
    {
        return new DefinitionException(
            "A decorator for '{$target}' carries the unknown key '{$unknown}' "
            . '(known keys: class, factory, arguments).',
        );
    }

    /**
     * A decorator entry carries exactly one shape key.
     */
    public static function missingShapeKey(string $target): DefinitionException
    {
        return new DefinitionException(
            "A decorator for '{$target}' carries none of the shape keys "
            . "(exactly one of 'class', 'factory' is required).",
        );
    }

    /**
     * A decorator entry may not carry both shape keys.
     *
     * @param list<string> $present
     */
    public static function doubledShapeKey(string $target, array $present): DefinitionException
    {
        return new DefinitionException(
            "A decorator for '{$target}' carries the shape keys '" . implode("', '", $present)
            . "' (exactly one of 'class', 'factory' is allowed).",
        );
    }

    /**
     * The decoration budget: the lane's hard bound on total declared
     * decorators, the reference container's own overflow posture.
     */
    public static function budgetExceeded(int $budget): DefinitionException
    {
        return new DefinitionException(
            "The decoration budget is exceeded ({$budget} decorators across every target).",
        );
    }

    /**
     * A decorator wraps the service it decorates -- the inner reference is
     * the opt-in that makes a declaration a decoration rather than a
     * rebinding.
     */
    public static function missingInner(string $target): DefinitionException
    {
        return new DefinitionException(
            "A decorator for '{$target}' never references '@inner' -- a decorator wraps the service "
            . "it decorates (add a ['service' => '@inner'] argument), or rebind the service instead.",
        );
    }

    /**
     * Decoration applies to a declared target, at the apply stage where the
     * definition set is in hand.
     */
    public static function unknownTarget(string $target): DefinitionException
    {
        return new DefinitionException(
            "Cannot decorate the unknown service '{$target}' "
            . "(declare '{$target}' under 'services' first).",
        );
    }

    /**
     * The lane's synthetic namespace is its own -- a declared identifier
     * may not squat in it.
     */
    public static function namespaceOccupied(string $id): DefinitionException
    {
        return new DefinitionException(
            "The service identifier '{$id}' occupies the decoration lane's own '@decor:' namespace "
            . '-- spell a host identifier instead.',
        );
    }

    /**
     * The lane runs after the autowire compile pass -- an unplanned target
     * is a misuse of the apply, not a boot-time state.
     */
    public static function unplanned(string $target): DefinitionException
    {
        return new DefinitionException(
            "Cannot decorate '{$target}' before the autowire compile pass has planned the definition set.",
        );
    }

    /**
     * One application's own synthetic spellings may not collide with a
     * declared identifier.
     */
    public static function collision(string $id): DefinitionException
    {
        return new DefinitionException(
            "The service identifier '{$id}' collides with the decoration lane's synthetic spelling "
            . 'for this target -- spell a host identifier instead.',
        );
    }
}
