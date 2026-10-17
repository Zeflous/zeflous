<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `class` shape's `arguments` spelling -- the positional plan, the
 * name-keyed hybrid, or the absent key's empty plan (see
 * {@see InstanceArguments} for the form dispatch).
 *
 * The absent key answers the empty positional plan; a list answers the
 * hand-written plan verbatim ({@see DefinitionArguments::assertValid()});
 * a string-keyed map answers the unplanned hybrid form
 * ({@see NamedArguments::assert()} over the declaration's own names).
 * Anything else -- a non-array, a mixed-key array -- is the ambiguity
 * it is: refused with the service in the message.
 */
final readonly class ArgumentsForm
{
    /**
     * @param class-string $class
     */
    public static function definition(
        string $id,
        string $class,
        mixed $arguments,
        ?ServiceLifetime $serviceLifetime = null,
    ): ServiceDefinition {
        if ($arguments === null) {
            return new InstanceDefinition($id, $class, [], $serviceLifetime ?? ServiceLifetime::SINGLETON);
        }

        if (!\is_array($arguments)) {
            self::refuse($id, 'arguments must be a list, ' . get_debug_type($arguments) . ' given');
        }

        if (array_is_list($arguments)) {
            return new InstanceDefinition(
                $id,
                $class,
                DefinitionArguments::assertValid($id, $arguments),
                $serviceLifetime ?? ServiceLifetime::SINGLETON,
            );
        }

        return new UnplannedInstanceDefinition(
            $id,
            $class,
            NamedArguments::assert($id, $arguments),
            $serviceLifetime ?? ServiceLifetime::SINGLETON,
        );
    }

    private static function refuse(string $id, string $reason): never
    {
        throw new DefinitionException("Service '{$id}' {$reason}.");
    }
}
