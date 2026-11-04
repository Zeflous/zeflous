<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * One reflected table's name: the grammar fold over the identifier
 * grammar the builder already spells -- a single validated segment
 * ({@see SqlIdentifier}'s `[A-Za-z_][A-Za-z0-9_]{0,63}` vocabulary),
 * refused at construction so a misspelled ask surfaces before the
 * first question ever travels to the socket.
 *
 * The value carries the BARE name, not the quoted spelling: the
 * reflection's dialect folds interpolate the name into their
 * single-quoted literals, and the grammar's own vocabulary -- no
 * quote, no dot, no whitespace -- is what makes that interpolation
 * safe without a second escaping layer. A name the grammar refuses
 * never reaches a spell.
 */
final readonly class TableName
{
    public function __construct(public string $name)
    {
        // The quote fold is reached one-way for its VALIDATION: it
        // answers the double-quoted spelling the builder consumes,
        // and the value keeps the bare name the reflection's spells
        // interpolate -- one grammar, two consumers, no duplication.
        new SqlIdentifier()->quote($name, 'table name');
    }
}
