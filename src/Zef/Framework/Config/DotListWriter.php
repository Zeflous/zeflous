<?php

declare(strict_types=1);

namespace Zef\Framework\Config;

/**
 * Copy-on-write list mutations for the configuration layer.
 *
 * The list-specialised counterpart of {@see DotWriter}: appending to or
 * prepending to a dot-notation addressed list is only legal when the target
 * exists and really is a list ({@see DotListLocator} enforces that,
 * fail-closed), and the write itself is delegated to {@see DotWriter::set()}.
 */
final readonly class DotListWriter
{
    /**
     * Appends the value to the list addressed by the segment path.
     *
     * The target must exist and be a list: a missing path, a scalar or an
     * associative map throws, because appending to an unknown shape would
     * silently guess the author's intent (fail closed).
     *
     * @param array<array-key, mixed> $data
     * @param non-empty-list<string>  $segments
     *
     * @return array<array-key, mixed>
     */
    public static function appendAt(array $data, array $segments, mixed $value): array
    {
        $target = DotListLocator::target($data, $segments, 'append to');

        return DotWriter::set($data, $segments, [...$target, $value]);
    }

    /**
     * Prepends the value to the list addressed by the segment path.
     *
     * The same fail-closed contract as {@see appendAt()}: the target must
     * exist and be a list.
     *
     * @param array<array-key, mixed> $data
     * @param non-empty-list<string>  $segments
     *
     * @return array<array-key, mixed>
     */
    public static function prependAt(array $data, array $segments, mixed $value): array
    {
        $target = DotListLocator::target($data, $segments, 'prepend to');

        return DotWriter::set($data, $segments, [$value, ...$target]);
    }
}
