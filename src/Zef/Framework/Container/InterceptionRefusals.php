<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The interception lane's refusal vocabulary: every fail-closed message
 * the knobs' parse, the materialisation and the arming can raise, in
 * one class of word-owning units (the {@see DecoratorRefusals}
 * precedent -- the lane stays logic-only, the words stay testable).
 *
 * A throwing interceptor or listener is deliberately absent from this
 * vocabulary: the resolution lane has never wrapped a factory's own
 * failure, and the interception lane keeps that discipline -- the
 * host's machinery throws with the host's own stack, raw.
 */
final readonly class InterceptionRefusals
{
    /** The type-given tail the shape refusals share (one spelling). */
    private const string GIVEN = ' given.';

    /**
     * The interceptors knob must be a positional list of entries.
     */
    public static function interceptorsNotAList(string $key, mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration must be a positional list of interceptor entries, "
            . get_debug_type($source) . self::GIVEN,
        );
    }

    /**
     * The declared interceptors exceed the lane's hard bound.
     */
    public static function interceptorBudgetExceeded(int $budget): DefinitionException
    {
        return new DefinitionException(
            "The 'interceptors' configuration declares more than {$budget} entries "
            . "-- the lane's hard bound.",
        );
    }

    /**
     * The listeners knob must be a map of the two known kinds.
     */
    public static function listenersNotAMap(string $key, mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration must be a map of listener kinds (resolving, resolved), "
            . get_debug_type($source) . self::GIVEN,
        );
    }

    /**
     * The listeners knob carries a kind outside its two-key vocabulary.
     */
    public static function unknownListenerKind(string $key, string $unknown): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration carries the unknown listener kind '{$unknown}' "
            . '(known kinds: resolving, resolved).',
        );
    }

    /**
     * One listener kind's value must be a positional list of entries.
     */
    public static function listenerListNotAList(string $key, string $kind, mixed $list): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.{$kind}' configuration must be a positional list of listener entries, "
            . get_debug_type($list) . self::GIVEN,
        );
    }

    /**
     * The declared listeners of one kind exceed the lane's hard bound.
     */
    public static function listenerBudgetExceeded(string $kind, int $budget): DefinitionException
    {
        return new DefinitionException(
            "The 'listeners.{$kind}' configuration declares more than {$budget} entries "
            . "-- the lane's hard bound.",
        );
    }

    /**
     * Each entry must itself be a map.
     */
    public static function entryNotAMap(string $label, mixed $entry): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' must be a map, "
            . get_debug_type($entry) . self::GIVEN,
        );
    }

    /**
     * The interceptor entry grammar accepts exactly four keys.
     */
    public static function unknownInterceptorKey(string $label, string $unknown): DefinitionException
    {
        return new DefinitionException(
            "The interceptor entry '{$label}' carries the unknown key '{$unknown}' "
            . '(known keys: class, factory, arguments, priority).',
        );
    }

    /**
     * The listener entry grammar accepts exactly three keys.
     */
    public static function unknownListenerKey(string $label, string $unknown): DefinitionException
    {
        return new DefinitionException(
            "The listener entry '{$label}' carries the unknown key '{$unknown}' "
            . '(known keys: class, factory, arguments).',
        );
    }

    /**
     * A listener entry refuses `priority`: the firing order is the
     * declaration order.
     */
    public static function listenerPriority(string $label): DefinitionException
    {
        return new DefinitionException(
            "The listener entry '{$label}' carries the 'priority' key -- listeners fire "
            . 'in declaration order, the honest spelling a priority would blur.',
        );
    }

    /**
     * An entry must carry exactly one shape key.
     */
    public static function missingShapeKey(string $label): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' carries no shape key -- exactly one of "
            . '(class, factory) must be declared.',
        );
    }

    /**
     * An entry cannot carry both shape keys.
     *
     * @param list<string> $present
     */
    public static function doubledShapeKey(string $label, array $present): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' carries both shape keys ("
            . implode(', ', $present) . ') -- exactly one must be declared.',
        );
    }

    /**
     * A class shape's class must be a non-empty string.
     */
    public static function classNotANonEmptyString(string $label, mixed $class): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' class must be a non-empty class name, "
            . get_debug_type($class) . self::GIVEN,
        );
    }

    /**
     * A class shape's class must exist at parse time.
     */
    public static function classDoesNotExist(string $label, string $class): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' class '{$class}' does not exist.",
        );
    }

    /**
     * The interceptor entry's priority must be an integer.
     */
    public static function priorityNotAnInteger(string $label, mixed $priority): DefinitionException
    {
        return new DefinitionException(
            "The interceptor entry '{$label}' priority must be an integer, "
            . get_debug_type($priority) . self::GIVEN,
        );
    }

    /**
     * The class entry's declared plan runs past the constructor's last
     * parameter -- the surplus arguments would be silently swallowed by
     * the engine's own invocation.
     */
    public static function tooManyArguments(string $label, string $class, int $parameters): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' class '{$class}' declares more arguments than the "
            . "constructor carries ({$parameters}).",
        );
    }

    /**
     * The class entry's declared plan stops before a required
     * parameter -- the constructor cannot answer it itself.
     */
    public static function stopsBeforeRequired(string $label, string $class, string $parameter): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' class '{$class}' stops before the required parameter "
            . "'{$parameter}' -- declare its argument, or give the constructor a default.",
        );
    }

    /**
     * A materialised entry must implement the contract its knob
     * demands -- the build-time proof (the middleware stack's own
     * discipline: a non-conforming entry fails the boot closed).
     */
    public static function notImplementingContract(string $label, string $contract, mixed $built): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' built "
            . get_debug_type($built) . ', which does not implement the ' . $contract
            . ' contract the knob demands.',
        );
    }

    /**
     * A factory-shaped entry must build an object -- the contracts the
     * knobs demand are interfaces, and only an object can carry one.
     */
    public static function builtNotAnObject(string $label, mixed $built): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' factory built "
            . get_debug_type($built) . ', an interception builder must answer an object.',
        );
    }

    /**
     * A factory shape's registered target must resolve to an object
     * (the {@see FactoryClosures} discipline over the entry grammar).
     */
    public static function factoryTargetNotAnObject(string $label, string $target, mixed $service): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' factory service '{$target}' did not resolve to an object, "
            . get_debug_type($service) . self::GIVEN,
        );
    }

    /**
     * A hand-built entry carrying neither shape's spelling -- the
     * parse guarantees exactly one; the materialisation refuses the
     * misuse rather than guessing around it.
     */
    public static function shapeNotSpelled(string $label): DefinitionException
    {
        return new DefinitionException(
            "The interception entry '{$label}' carries neither a class nor a factory spelling -- "
            . 'exactly one must be declared.',
        );
    }

    /**
     * The interception registry arms once per boot.
     */
    public static function alreadyArmed(): DefinitionException
    {
        return new DefinitionException(
            "The container's interception registry is already armed -- the boot arms it once.",
        );
    }

    /**
     * The registry's run is the armed set's run -- an unarmed
     * registry has nothing to run a construction under.
     */
    public static function notArmed(): DefinitionException
    {
        return new DefinitionException(
            "The container's interception registry is not armed -- the boot arms it once, "
            . 'before the first resolution.',
        );
    }
}
