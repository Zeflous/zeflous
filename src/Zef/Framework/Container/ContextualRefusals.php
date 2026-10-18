<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The contextual-binding lane's refusal messages -- every way the knob or
 * an applied binding can fail the lane, each with the knob's own name (or
 * the binding's own consumer and dependency) and the way out in the
 * message.
 *
 * Kept its own archeology-sized unit the way the autoconfiguration lane's
 * refusals are ({@see Autowiring\AutoconfigRefusals}): the lane keeps the
 * verdicts, this class keeps the words.
 */
final readonly class ContextualRefusals
{
    /** The type-given tail the knob's shape refusals share (the S1192 line: one spelling). */
    private const string GIVEN = ' given.';

    public static function notAMap(string $key, mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration must be a map of consumers to dependency overrides, "
            . get_debug_type($source) . self::GIVEN,
        );
    }

    /**
     * @param int|string $consumer the entry's own key, named in the message
     */
    public static function consumerKey(string $key, int|string $consumer): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' keys must be non-empty consumer service identifiers, "
            . get_debug_type($consumer) . self::GIVEN,
        );
    }

    public static function depsNotAMap(string $key, string $consumer, mixed $deps): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' entry for consumer '{$consumer}' must be a map of dependency overrides, "
            . get_debug_type($deps) . self::GIVEN,
        );
    }

    /**
     * @param int|string $dep the override's own key, named in the message
     */
    public static function depKey(string $key, string $consumer, int|string $dep): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' overrides for consumer '{$consumer}' must be keyed by non-empty dependency "
            . 'service identifiers, ' . get_debug_type($dep) . self::GIVEN,
        );
    }

    public static function target(string $key, string $consumer, string $dep, mixed $target): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' binding for consumer '{$consumer}' dependency '{$dep}' must name a non-empty "
            . 'target service identifier, ' . get_debug_type($target) . self::GIVEN,
        );
    }

    public static function consumerUnknown(string $consumer): DefinitionException
    {
        return new DefinitionException(
            "Contextual binding: consumer '{$consumer}' is not a registered service.",
        );
    }

    public static function dependencyNotDeclared(string $consumer, string $dep): DefinitionException
    {
        return new DefinitionException(
            "Contextual binding: consumer '{$consumer}' does not declare the dependency '{$dep}' "
            . '(a binding overrides a service reference carried by the consumer arguments or calls).',
        );
    }

    public static function unplanned(string $consumer): DefinitionException
    {
        return new DefinitionException(
            "Contextual binding: consumer '{$consumer}' carries an unplanned constructor plan -- "
            . 'run the autowire compiler pass before applying contextual bindings.',
        );
    }

    public static function collision(string $via): DefinitionException
    {
        return new DefinitionException(
            "Contextual binding: the synthetic identifier '{$via}' is already taken "
            . '(reserved for contextual bindings; declare the target under another identifier).',
        );
    }
}
