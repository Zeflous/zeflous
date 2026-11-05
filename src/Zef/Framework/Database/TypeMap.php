<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The per-table type map: the immutable column-name-to-converter
 * vocabulary the typed gateway ({@see TypedTableGateway}) carries --
 * the value the value vocabulary's widening rides, opt-in per table
 * and never a global mode. A deployment spells the map for the tables
 * whose fidelity it wants (hand-declared, or composed from the
 * reflected declared types through {@see ReflectedTypeMap}); every
 * other table keeps the raw narrowing the port always answered.
 *
 * The map is the VALUE alone: the loudly-validated vocabulary, its
 * converters readable as the public spelling the row-application fold
 * ({@see TypeMapRows}) walks -- the split the archeology ceiling
 * demanded, the same question-split the criteria lane spelled first
 * ({@see RepositoryCriteria} over the builder, the value here). The
 * application itself is direction-owned: encode at query build, decode
 * after the port's narrowing, NULL passing untouched on both sides (a
 * nullable column's NULL is the database's own answer, not an encoded
 * anything).
 *
 * @api
 */
final readonly class TypeMap
{
    /**
     * The validated vocabulary, in the host's own column order.
     *
     * @var array<string, ColumnType>
     */
    public array $converters;

    /**
     * @param array<mixed> $converters column name => the converter
     *                                 ({@see ColumnType}) its cells spell; a
     *                                 non-string or empty column key refuses loudly,
     *                                 and so does an entry that is not a converter
     *
     * @throws TypeMappingException when a column key is not a non-empty
     *                              string, or an entry is not a converter
     */
    public function __construct(array $converters)
    {
        /** @var array<string, ColumnType> $validated the loudly-validated vocabulary */
        $validated = [];

        foreach ($converters as $column => $converter) {
            if (!\is_string($column) || $column === '') {
                throw new TypeMappingException(
                    "The type map's column key must be a non-empty string (got "
                    . get_debug_type($column) . ').',
                );
            }

            if (!$converter instanceof ColumnType) {
                throw new TypeMappingException(
                    "The type map's '{$column}' entry must be a ColumnType (got "
                    . get_debug_type($converter) . ').',
                );
            }

            $validated[$column] = $converter;
        }

        $this->converters = $validated;
    }
}
