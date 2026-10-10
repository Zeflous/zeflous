<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Schema;

/**
 * The schema value rules: the checks for the four value kinds the
 * configuration layer's data language uses (see `ROADMAP.md`,
 * "Configuration System + DSL + Radix Tree").
 *
 * The {@see ConfigSchema} engine owns the field rules -- which field is
 * required, which field carries which kind, and which keys the schema
 * declares at all; this class owns what a value of each kind must look
 * like:
 *
 * - `string` -- any non-empty string.
 * - `strings` -- a non-empty list of non-empty strings; every element is
 *   addressed by its offset (`method.0`).
 * - `string-map` -- a non-empty, non-list map of non-empty strings; every
 *   value is addressed by its key (`constraints.id`).
 * - `values` -- a non-empty, non-list map with free-form values; the shape
 *   is checked, the values are intentionally untyped (the home of
 *   free-form metadata).
 *
 * A present-but-degenerate value -- an empty string, an empty list, an
 * empty map -- is a violation: a declaration that carries nothing is a
 * defect, not a default. A list-shaped array is rejected by the map kinds
 * and a map-shaped array by the list kind, and a mixed-key map keeps its
 * integer keys as entry names, which stays consistent with the dot-notation
 * addressing of list offsets.
 *
 * The class is a static, single-purpose utility in the {@see ArrayMerger}
 * mould: pure functions over the data they are handed, no state, and the
 * words in {@see SchemaViolation::EXPECTATIONS} are the contract the engine quotes,
 * so a violation's `expected` never disagrees with the check that produced
 * it.
 */
final readonly class SchemaValueRules
{
    /**
     * Checks a present field value against its kind.
     *
     * @param 'string'|'string-map'|'strings'|'values' $kind
     *
     * @return list<SchemaViolation>
     */
    public static function check(string $field, string $kind, mixed $value): array
    {
        if ($kind !== 'string') {
            return self::collectionViolations($field, $kind, $value);
        }

        return self::checkString($field, $value);
    }

    /**
     * Checks a present collection field (`strings`, `string-map`, `values`)
     * against its kind: the shape first, then the elements where the kind
     * checks them.
     *
     * @param 'string-map'|'strings'|'values' $kind
     *
     * @return list<SchemaViolation>
     */
    private static function collectionViolations(string $field, string $kind, mixed $collection): array
    {
        $wantsList = $kind === 'strings';

        if (!\is_array($collection) || $collection === [] || array_is_list($collection) !== $wantsList) {
            return [new SchemaViolation($field, SchemaViolation::EXPECTATIONS[$kind], self::describe($collection))];
        }

        if ($kind === 'values') {
            return [];
        }

        return self::elementViolations($field, $collection);
    }

    /**
     * A `string` field: any non-empty string satisfies it.
     *
     * @return list<SchemaViolation>
     */
    private static function checkString(string $address, mixed $subject): array
    {
        if (!\is_string($subject) || $subject === '') {
            return [new SchemaViolation($address, SchemaViolation::EXPECTATIONS['string'], self::describe($subject))];
        }

        return [];
    }

    /**
     * Checks every element of a collection against the `string` rule, each
     * element addressed by its key (an offset for lists, a name for maps).
     *
     * @param array<array-key, mixed> $elements
     *
     * @return list<SchemaViolation>
     */
    private static function elementViolations(string $prefix, array $elements): array
    {
        $collected = [];

        foreach (array_keys($elements) as $offset) {
            foreach (self::checkString($prefix . '.' . $offset, $elements[$offset]) as $elementViolation) {
                $collected[] = $elementViolation;
            }
        }

        return $collected;
    }

    /**
     * Describes an offending value in one deterministic phrase: the degenerate
     * emptinesses read as themselves, everything else by its debug type.
     */
    private static function describe(mixed $offender): string
    {
        if ($offender === '') {
            return 'an empty string';
        }

        if ($offender === []) {
            return 'an empty array';
        }

        return get_debug_type($offender);
    }
}
