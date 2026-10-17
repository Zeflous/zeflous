<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `class` shape's arguments-form dispatch (the planning-decision
 * fork of {@see InstanceShape}).
 *
 * The form IS the decision, kept here so the shape parser stays the
 * class-grammar unit and this class stays the arguments-grammar unit:
 *
 * - **positional** (a list, or the absent key): the hand-written plan,
 *   carried by a plain {@see InstanceDefinition};
 * - **name-keyed** (a string-keyed map): the hybrid plan, carried by an
 *   {@see UnplannedInstanceDefinition} with the declaration's own
 *   arguments;
 * - **`'autowire' => true`** (without `arguments`): the whole-constructor
 *   request, carried by an {@see UnplannedInstanceDefinition} with a null
 *   map.
 *
 * Every other combination -- `autowire` beside `arguments`, `autowire`
 * with a value other than true, a non-array, a mixed-key array -- is the
 * ambiguity it is: refused with the service and the accepted forms in
 * the message.
 */
final readonly class InstanceArguments
{
    /**
     * @param class-string            $class
     * @param array<array-key, mixed> $entry
     */
    public static function definition(
        string $id,
        string $class,
        array $entry,
        ?ServiceLifetime $serviceLifetime = null,
        ?ServiceTags $serviceTags = null,
    ): ServiceDefinition {
        if (\array_key_exists('autowire', $entry)) {
            return AutowireKey::form($id, $class, $entry, $serviceLifetime, $serviceTags);
        }

        return ArgumentsForm::definition($id, $class, $entry['arguments'] ?? null, $serviceLifetime, $serviceTags);
    }
}
