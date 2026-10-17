<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * A declarative service definition (the container's configuration-driven
 * registration form; `ROADMAP.md`, CONTAINER -> "Registration").
 *
 * The class is the closed base of exactly four definition shapes, one per
 * declaration form the grammar accepts:
 *
 * - {@see InstanceDefinition}: `['class' => FQCN, 'arguments' => [...]]`
 * - {@see FactoryDefinition}: `['factory' => 'Target::method', 'arguments' => [...]]`
 * - {@see AliasDefinition}: `['alias' => 'another.id']`
 * - {@see ValueDefinition}: `['value' => mixed]`
 *
 * The static {@see fromArray()} parser is the single entry point: it
 * validates the grammar fail-closed (unknown keys rejected with the known
 * list, exactly one shape key required, existing classes only) and answers
 * the matching subclass. Construction through the subclasses' constructors
 * stays public for hand-built definitions, with the same identifier
 * invariants enforced.
 */
abstract readonly class ServiceDefinition
{
    public function __construct(
        public string $id,
        /** How many instances this service's declarations may ever observe; the default is the behaviour every earlier declaration already had. */
        public ServiceLifetime $lifetime = ServiceLifetime::SINGLETON,
    ) {
        if ($id === '') {
            throw new DefinitionException('A service definition requires a non-empty identifier.');
        }
    }

    /**
     * Parses one `services` map entry into its definition object.
     *
     * Every ambiguity fails closed with the service's own identifier in the
     * message: a non-map entry, an unknown key (with the known key list), a
     * missing or doubled shape key, a class that does not exist, a malformed
     * factory string, or arguments attached to a shape that takes none.
     *
     * @param mixed $entry the raw definition map
     */
    public static function fromArray(string $id, mixed $entry): self
    {
        if ($id === '') {
            throw new DefinitionException('A service definition requires a non-empty identifier.');
        }

        if (!\is_array($entry)) {
            throw new DefinitionException(
                "Service '{$id}' must be a definition map, " . get_debug_type($entry) . ' given.',
            );
        }

        $unknown = DefinitionKeys::unknownIn($entry, DefinitionKeys::DEFINITION);
        if ($unknown !== null) {
            throw new DefinitionException(
                "Service '{$id}' carries the unknown key '{$unknown}' (known keys: "
                . DefinitionKeys::spelled(DefinitionKeys::DEFINITION) . ').',
            );
        }

        return DefinitionShapes::fromEntry($id, $entry);
    }
}
