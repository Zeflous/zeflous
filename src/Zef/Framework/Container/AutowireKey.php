<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `'autowire' => true` request's own parser (the whole-constructor
 * planning form; see {@see InstanceArguments} for the form dispatch).
 *
 * The key is the request itself: it stands alone (beside `arguments`
 * it is the ambiguity of two planning forms, refused), it spells true
 * (any other value is a redundant or mistyped declaration, refused),
 * and it answers the unplanned form with a null name map -- plan the
 * whole constructor.
 */
final readonly class AutowireKey
{
    /**
     * @param class-string            $class
     * @param array<array-key, mixed> $entry
     */
    public static function form(
        string $id,
        string $class,
        array $entry,
        ?ServiceLifetime $serviceLifetime = null,
    ): UnplannedInstanceDefinition {
        if (\array_key_exists('arguments', $entry)) {
            self::refuse($id, "carries both 'autowire' and 'arguments' "
                . '(choose one planning form: the name-keyed arguments, or the whole-constructor request)');
        }

        if ($entry['autowire'] !== true) {
            self::refuse($id, "carries the 'autowire' key with a value other than true "
                . '(the key is the whole-constructor planning request; an absent key is the default)');
        }

        return new UnplannedInstanceDefinition($id, $class, null, $serviceLifetime ?? ServiceLifetime::SINGLETON);
    }

    private static function refuse(string $id, string $reason): never
    {
        throw new DefinitionException("Service '{$id}' {$reason}.");
    }
}
