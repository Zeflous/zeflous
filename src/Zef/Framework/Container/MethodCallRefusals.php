<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The method-call injection lane's refusal messages -- every way a
 * `calls` declaration can fail the grammar, each with the service's own
 * identifier and the way out in the message.
 *
 * Kept its own archeology-sized unit the way the contextual lane's
 * refusals are ({@see ContextualRefusals}): the lane keeps the verdicts,
 * this class keeps the words.
 */
final readonly class MethodCallRefusals
{
    /** The type-given tail the shape refusals share (the S1192 line: one spelling). */
    private const string GIVEN = ' given.';

    public static function notAMap(string $id, mixed $calls): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' calls must be a list, " . get_debug_type($calls) . self::GIVEN,
        );
    }

    public static function notAPositionalList(string $id): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' calls must be a positional list (string keys are rejected).",
        );
    }

    public static function entryNotAMap(string $id, mixed $call): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' call entry must be a map, " . get_debug_type($call) . self::GIVEN,
        );
    }

    public static function unknownEntryKey(string $id, string $unknown): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' call entry carries the unknown key '{$unknown}' (known keys: method, arguments).",
        );
    }

    public static function methodNotAName(string $id, mixed $method): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' call entry method must be a non-empty method name, "
            . get_debug_type($method) . self::GIVEN,
        );
    }

    public static function methodMissing(string $id, string $class, string $method): DefinitionException
    {
        return new DefinitionException(
            "Service '{$id}' call method '{$method}' does not exist on '{$class}'.",
        );
    }
}
