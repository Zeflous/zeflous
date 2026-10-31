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

            /** @var array<string, null|bool|float|int|string> $row the row's string-keyed scalar cells */
            $row = [];

            /** @var null|bool|int|float|string $value one fetched value, the scalar answer set */
            foreach ($fetchedRow as $column => $value) {
                if (!\is_string($column)) {
                    throw new QueryException('The driver answered a non-string column key under FETCH_ASSOC.');
                }

                $row[$column] = $value;
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
