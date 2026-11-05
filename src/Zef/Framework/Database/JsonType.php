<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use JsonException;
use Override;

/**
 * The JSON column's converter: the array (or scalar) value in, the
 * encoded string out; the encoded cell in, the decoded value back --
 * under {@see \JSON_THROW_ON_ERROR} both ways, the tree's own encode
 * lineage ({@see \Zef\Framework\Http\JsonBodyParser} on the read side,
 * the log family's writers on the write side).
 *
 * The write vocabulary is the row vocabulary's own: arrays and
 * scalars (`null|bool|float|int|string` and the lists/maps over them)
 * -- what {@see QueryValues::assertScalar()} already carries plus the
 * array shapes JSON exists to hold. Anything else refuses with the
 * type named: an object is the caller's hydration question (decode it
 * to arrays first -- the read direction answers arrays, never
 * stdClass, so accepting one on write would round-trip into a
 * different shape than the caller handed in), and a resource or NAN
 * is a value JSON cannot spell at all.
 *
 * The read direction decodes STRING cells only. A non-string,
 * non-null cell refuses loudly: a JSON column's cells are its own
 * encoded text, and a bare scalar cell is the SQLite numeric-affinity
 * trap (a `JSON`-declared column carries NUMERIC affinity, so a
 * stored `'123'` travels back as int) -- the honest guidance a
 * deployment needs at the boundary, not a silent cast that would
 * answer a value the cell never spelled.
 *
 * @api
 */
final class JsonType implements ColumnType
{
    /**
     * The decode recursion ceiling: the ext-json default depth, the
     * widest vocabulary the boundary accepts (a deeper document
     * refuses loudly rather than silently truncating).
     */
    private const int DEPTH = 512;

    #[Override]
    public function toDatabase(mixed $value): string
    {
        if (
            !\is_array($value) && $value !== null && !\is_bool($value)
            && !\is_float($value) && !\is_int($value) && !\is_string($value)
        ) {
            throw new TypeMappingException(
                "The JSON column's write value must be an array or a scalar (got "
                . get_debug_type($value) . ') -- decode objects to arrays before the boundary.',
            );
        }

        try {
            return json_encode($value, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new TypeMappingException(
                "The JSON column's write value cannot be encoded (" . $jsonException->getMessage() . ').',
                0,
                $jsonException,
            );
        }
    }

    #[Override]
    public function toPhp(mixed $value): mixed
    {
        if (!\is_string($value)) {
            throw new TypeMappingException(
                "The JSON column's stored cell must be a string (got "
                . get_debug_type($value) . ') -- a bare scalar cell is the SQLite numeric-affinity trap,'
                . ' not an encoded value.',
            );
        }

        try {
            return json_decode($value, true, self::DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new TypeMappingException(
                "The JSON column's stored cell '{$value}' is malformed JSON"
                . ' -- the named refusal, never a silent null.',
                0,
                $jsonException,
            );
        }
    }
}
