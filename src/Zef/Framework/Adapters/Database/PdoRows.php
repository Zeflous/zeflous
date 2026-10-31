<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Database;

use Zef\Framework\Database\QueryException;

/**
 * The FETCH_ASSOC answer's narrowing fold: the engine contract,
 * enforced rather than assumed -- exactly one string-keyed map of
 * scalar cells per row, and a driver that breaks the contract halts
 * with the shape named instead of leaking a mixed row to every
 * consumer downstream (the {@see \Zef\Framework\Event\DeliveryRecordValues}
 * walk's own discipline over its decoder's answer set).
 *
 * @internal
 */
final class PdoRows
{
    /**
     * Narrows the driver's raw FETCH_ASSOC answer to the port's
     * promised row list.
     *
     * @param array<int|string, mixed> $fetched the raw statement answer
     *
     * @return list<array<string, null|bool|float|int|string>>
     */
    public static function narrow(array $fetched): array
    {
        $rows = [];
        foreach ($fetched as $fetchedRow) {
            if (!\is_array($fetchedRow)) {
                throw new QueryException('The driver answered a non-array row under FETCH_ASSOC.');
            }

            $row = [];

            /** @var mixed $value one fetched cell, judged by the scalar guard below */
            foreach ($fetchedRow as $column => $value) {
                if (!\is_string($column)) {
                    throw new QueryException('The driver answered a non-string column key under FETCH_ASSOC.');
                }

                if (
                    $value !== null && !\is_bool($value) && !\is_float($value)
                    && !\is_int($value) && !\is_string($value)
                ) {
                    throw new QueryException('The driver answered a non-scalar cell value under FETCH_ASSOC.');
                }

                $row[$column] = $value;
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
