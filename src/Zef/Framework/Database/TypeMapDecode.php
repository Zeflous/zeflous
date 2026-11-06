<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The type map's read-direction fold: the row-application verbs that
 * decode the port's narrowed rows back to the PHP-side vocabulary,
 * after the port's own narrowing -- the {@see RepositoryCriteria}
 * idiom, one direction one fold, the write direction's sibling ({@see
 * TypeMapEncode}).
 *
 * The null cell passes untouched -- a nullable column answers NULL
 * before it answers anything a converter could decode -- and columns
 * the map does not spell travel as the port answered them: the map
 * widens the columns it names, and owns no opinion about the rest.
 *
 * @api
 */
final class TypeMapDecode
{
    /**
     * The read direction over one row: the mapped columns decoded,
     * every other column travelling as the port answered it.
     *
     * @param array<string, mixed> $row the narrowed row the port answers
     *
     * @return array<string, mixed> the decoded row
     *
     * @throws TypeMappingException when a mapped column's cell is not
     *                              the shape its converter's read direction accepts
     */
    public static function row(TypeMap $typeMap, array $row): array
    {
        $decoded = $row;

        foreach ($row as $column => $value) {
            if ($value === null || !isset($typeMap->converters[$column])) {
                continue;
            }

            $decoded[$column] = $typeMap->converters[$column]->toPhp($value);
        }

        return $decoded;
    }

    /**
     * The read direction over a row list: each narrowed row decoded,
     * the list's shape and order preserved.
     *
     * @param list<array<string, mixed>> $rows the narrowed rows the port answers
     *
     * @return list<array<string, mixed>> the decoded rows
     *
     * @throws TypeMappingException when a mapped column's cell is not
     *                              the shape its converter's read direction accepts
     */
    public static function rows(TypeMap $typeMap, array $rows): array
    {
        $decoded = [];

        foreach ($rows as $row) {
            $decoded[] = self::row($typeMap, $row);
        }

        return $decoded;
    }
}
