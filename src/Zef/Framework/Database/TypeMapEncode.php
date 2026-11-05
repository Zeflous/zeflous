<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The type map's write-direction fold: the row-application verb that
 * encodes a host write payload's mapped columns to the driver-side
 * vocabulary, at query build -- the {@see RepositoryCriteria} idiom (a
 * static fold over the subject it walks), one direction one fold, the
 * split the archeology ceiling demanded (the schema family's own
 * flag-fold lesson).
 *
 * The null value passes untouched -- a nullable column's NULL is the
 * database's own answer, not an encoded anything -- and columns the
 * map does not spell travel as the host spelled them: the map widens
 * the columns it names, and owns no opinion about the rest.
 *
 * @api
 */
final class TypeMapEncode
{
    /**
     * The write direction over one row: the mapped columns encoded,
     * every other column travelling as the host spelled it.
     *
     * @param array<string, mixed> $row the host's write payload
     *
     * @return array<string, mixed> the encoded payload
     *
     * @throws TypeMappingException when a mapped column's value is not
     *                              the shape its converter's write direction accepts
     */
    public static function row(TypeMap $typeMap, array $row): array
    {
        $encoded = $row;

        foreach ($row as $column => $value) {
            if ($value === null || !isset($typeMap->converters[$column])) {
                continue;
            }

            $encoded[$column] = $typeMap->converters[$column]->toDatabase($value);
        }

        return $encoded;
    }
}
