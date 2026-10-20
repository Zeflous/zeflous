<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Container\DefinitionException;

/**
 * The mode knob's refusal messages -- every way the `container.mode`
 * grammar can fail the parse, each naming the knob and the way out.
 *
 * Kept its own archeology-sized unit the way every lane's refusals
 * are ({@see Container\Aot\AotRefusals}): the verdict
 * unit ({@see ContainerMode}) keeps the fold, this class keeps the
 * words -- and the vocabulary the unknown-spelling refusal spells is
 * the same closed list the fold matches against, declared once here
 * as the single source both consult.
 */
final readonly class ContainerModeRefusals
{
    /**
     * The knob's closed vocabulary, declaration order: the two modes
     * and the environment-derived default.
     */
    public const array SPELLINGS = ['development', 'production', 'auto'];

    public static function notAMap(string $key, mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration must be a map of container keys, "
            . get_debug_type($source) . ' given.',
        );
    }

    /**
     * @param list<array-key> $unknown
     */
    public static function unknownKeys(string $key, array $unknown): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration carries the unknown key '"
            . implode("', '", array_map(strval(...), $unknown))
            . "' (known keys: mode).",
        );
    }

    public static function modeNotAString(string $key, mixed $mode): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.mode' configuration must be a string, "
            . get_debug_type($mode) . ' given.',
        );
    }

    public static function unknownSpelling(string $key, string $mode): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.mode' configuration must be one of '" . implode("', '", self::SPELLINGS)
            . "' (case-insensitive); '{$mode}' given.",
        );
    }
}
