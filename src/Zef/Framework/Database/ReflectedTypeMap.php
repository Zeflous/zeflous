<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The reflected map entry: schema reflection's second consumer -- the
 * fold that reads a reflected {@see TableSchema}'s declared types and
 * answers the {@see TypeMap} they spell, so a deployment composes its
 * table's fidelity from what the driver itself declared instead of
 * hand-spelling the same columns twice. The fold is pure over the
 * reflected value (the host composes it over the live reflection:
 * `ReflectedTypeMap::fromSchema($schemaReflection->table(...))`).
 *
 * The matching vocabulary is CLOSED, and honestly so: the whole-second
 * datetime spellings (`DATETIME`, `TIMESTAMP`) and the JSON word
 * (`JSON`) -- the spellings the live CI socket's own dialect answers,
 * matched case-insensitively as the driver spells them. A precision
 * spelling (`datetime(6)`), a multiword PostgreSQL spelling
 * (`timestamp without time zone`), `JSONB`, `DATE` and every decimal
 * spelling map to NO entry: the raw narrowing keeps them exactly as
 * the port answered them. DECIMALS STAY STRINGS deliberately -- the
 * reference's own default posture (a float would lie about precision
 * at the boundary, and a silent lie is the exact failure class this
 * family exists to close); a date-only converter is the named
 * follow-up the day a consumer asks, because the whole-second
 * canonical spelling would rewrite a day-only cell.
 *
 * @api
 */
final class ReflectedTypeMap
{
    /** The whole-second datetime spellings, lowercased. */
    private const array DATETIME_WORDS = ['datetime', 'timestamp'];

    /** The JSON spellings, lowercased. */
    private const array JSON_WORDS = ['json'];

    /**
     * Answers the map one reflected table's declared types spell.
     *
     * @throws TypeMappingException never -- a reflected column's name
     *                              is never empty (the value's own constructor refuses it),
     *                              and the fold only ever composes the two built-in converters
     */
    public static function fromSchema(TableSchema $tableSchema): TypeMap
    {
        /** @var array<string, mixed> $converters the spellings this table's columns answer */
        $converters = [];

        foreach ($tableSchema->columns as $column) {
            $declared = mb_strtolower($column->declaredType);

            if (\in_array($declared, self::DATETIME_WORDS, true)) {
                $converters[$column->name] = new DateTimeType();
            } elseif (\in_array($declared, self::JSON_WORDS, true)) {
                $converters[$column->name] = new JsonType();
            }
        }

        return new TypeMap($converters);
    }
}
