<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * One column type's two conversion verbs: the contract a {@see TypeMap}
 * entry spells, the value vocabulary's widening seam -- one converter
 * per column, hand-declared or composed from the reflected declared
 * types ({@see ReflectedTypeMap}), a host's own implementation the
 * open extension the interface exists for (the {@see Condition} seam's
 * own posture: the tree's vocabulary closed, the composition open).
 *
 * The two directions own their vocabularies symmetrically. The write
 * direction takes the PHP-side value the column's fidelity wants (the
 * immutable datetime, the decodable array) and answers the driver-side
 * scalar the statement's parameter grammar already accepts
 * ({@see QueryValues::assertScalar()}). The read direction takes the
 * narrowed driver-side cell ({@see \Zef\Framework\Adapters\Database\PdoRows},
 * `null|bool|float|int|string`) and answers the PHP-side value back.
 *
 * NULL never reaches a converter in either direction: the map answers
 * the null cell before the converter sees it, because a nullable
 * column's NULL is the database's own answer -- not an encoded
 * anything. Every other shape a direction does not accept refuses
 * loudly ({@see TypeMappingException}, the offending spelling named),
 * never a silent coercion that would lie about the value it answered.
 *
 * @api
 */
interface ColumnType
{
    /**
     * The write direction: the PHP-side value encoded to the
     * driver-side scalar vocabulary the statement carries.
     *
     * @param mixed $value the PHP-side value the column's map spells
     *
     * @return null|bool|float|int|string the driver-side scalar
     *
     * @throws TypeMappingException when the value is not the shape this
     *                              column's write direction accepts
     */
    public function toDatabase(mixed $value): bool|float|int|string|null;

    /**
     * The read direction: the narrowed driver-side cell decoded back
     * to the PHP-side value.
     *
     * @param mixed $value the narrowed cell the port's row shape answers
     *                     (`null|bool|float|int|string`; the map answers the null cell
     *                     before the converter sees it)
     *
     * @return mixed the PHP-side value
     *
     * @throws TypeMappingException when the cell is not the shape this
     *                              column's read direction accepts
     */
    public function toPhp(mixed $value): mixed;
}
