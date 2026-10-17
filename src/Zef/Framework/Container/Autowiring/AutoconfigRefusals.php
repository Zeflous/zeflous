<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\InstanceDefinition;

/**
 * The autoconfiguration lane's refusal messages -- every way the knob
 * or a declaration can fail the lane, each with the knob's own name
 * (or the declaration's identifier) and the way out in the message.
 *
 * Kept its own archeology-sized unit the way the planner's refusals
 * are ({@see AutowireRefusals}): the lane keeps the verdicts, this
 * class keeps the words.
 */
final readonly class AutoconfigRefusals
{
    /** The type-given tail the knob's shape refusals share (the S1192 line: one spelling). */
    private const string GIVEN = ' given.';

    public static function notAMap(string $key, mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration must be a map of autoconfiguration keys, "
            . get_debug_type($source) . self::GIVEN,
        );
    }

    public static function unknownKey(string $key, string $unknown): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration carries the unknown key '{$unknown}' (known keys: interfaces).",
        );
    }

    public static function interfacesNotAMap(string $key, mixed $interfaces): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.interfaces' configuration must be a map of interface names to tags, "
            . get_debug_type($interfaces) . self::GIVEN,
        );
    }

    public static function interfacesList(string $key): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.interfaces' configuration must be a map of interface names to tags, list given.",
        );
    }

    public static function keyNotInterfaceName(string $key, int|string $interface): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.interfaces' keys must be non-empty interface names, "
            . get_debug_type($interface) . self::GIVEN,
        );
    }

    public static function missingInterface(string $key, string $interface): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.interfaces' key '{$interface}' does not name an existing interface.",
        );
    }

    /**
     * @param int|string $interface the rule's own key, named in the message
     */
    public static function tagNotString(string $key, int|string $interface, mixed $tag): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}.interfaces' rule for '{$interface}' must spell a tag string, "
            . get_debug_type($tag) . self::GIVEN,
        );
    }

    /**
     * @param int|string $interface the rule's own key, named in the message
     */
    public static function tagRefused(
        string $key,
        int|string $interface,
        DefinitionException $definitionException,
    ): DefinitionException {
        return new DefinitionException(
            "The '{$key}.interfaces' rule for '{$interface}' spells a tag the grammar refuses: "
            . $definitionException->getMessage(),
            $definitionException->getCode(),
            $definitionException,
        );
    }

    /**
     * A tag several sources would hand one declaration: the collision
     * the boot refuses, every source named.
     *
     * @param list<string> $sources the sources claiming the tag
     */
    public static function collision(
        InstanceDefinition $instanceDefinition,
        string $tag,
        array $sources,
    ): AutowiringException {
        return new AutowiringException(
            "Service '{$instanceDefinition->id}' would carry the tag '{$tag}' from several sources ("
            . implode('; ', $sources)
            . ') -- declare the tag once, from one source.',
        );
    }
}
