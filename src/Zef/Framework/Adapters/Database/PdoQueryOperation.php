<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

/**
 * The query-log emission's operation fold: the word a statement's own
 * head answers, folded the same way the unbounded-statement audit's
 * own operation fold reads its statements ({@see PdoUnboundedAudit})
 * -- the prefix match over the upper-cased, left-trimmed head, no
 * boundary guard, because no valid SQL statement begins with the
 * letters of one operation word while being another.
 *
 * The vocabulary is closed at five words -- select, insert, update,
 * delete, and 'other' for everything a statement head can spell
 * beyond the four (DDL, EXPLAIN, admin statements) -- so the
 * dimension the emission's series carry stays a bounded space, the
 * same finiteness the meter's own contract demands of its identity
 * space.
 *
 * @internal
 */
final class PdoQueryOperation
{
    /**
     * The four head words an ordinary data statement spells, mapped to
     * the dimension vocabulary the emission carries.
     */
    private const array OPERATIONS = [
        'SELECT' => 'select',
        'INSERT' => 'insert',
        'UPDATE' => 'update',
        'DELETE' => 'delete',
    ];

    /**
     * The statement's operation word, folded from its own head.
     */
    public static function word(string $sql): string
    {
        $head = mb_strtoupper(mb_ltrim($sql));

        foreach (self::OPERATIONS as $prefix => $operation) {
            if (str_starts_with($head, $prefix)) {
                return $operation;
            }
        }

        return 'other';
    }
}
