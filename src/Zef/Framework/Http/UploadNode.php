<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Walks the parallel-array upload specification.
 *
 * The error axis drives the recursion -- it is the one member PHP
 * guarantees for every real upload: a node whose error axis is a scalar
 * is a leaf, built by {@see UploadLeaf}, and a node whose error axis is
 * an array is a subtree whose every key walks the same way. The walk
 * iterates the error axis by key and reads each sibling axis value
 * straight into the recursion's argument -- the value never lands in a
 * variable, keeping the mixed SAPI state out of local scope. The
 * reference implementation's recursion, move-verbatim.
 */
final class UploadNode
{
    /**
     * Walks one node of the parallel-array specification.
     *
     * @param mixed $name  the client filename axis at this node
     * @param mixed $type  the client media type axis at this node
     * @param mixed $tmp   the temporary file axis at this node
     * @param mixed $error the error axis at this node, the recursion driver
     * @param mixed $size  the client-declared size axis at this node
     *
     * @return array<array-key, mixed>|UploadedFile the leaf or the subtree at this node
     */
    public static function walk(mixed $name, mixed $type, mixed $tmp, mixed $error, mixed $size): array|UploadedFile
    {
        if (!\is_array($error)) {
            return UploadLeaf::build($name, $type, $tmp, $error, $size);
        }

        $branch = [];

        foreach (array_keys($error) as $key) {
            $branch[$key] = self::walk(
                self::axis($name, $key),
                self::axis($type, $key),
                self::axis($tmp, $key),
                $error[$key],
                self::axis($size, $key),
            );
        }

        return $branch;
    }

    /**
     * Answers the value one parallel axis carries for a key, or null when
     * the axis is not an array or lacks the key.
     *
     * @param mixed $axis the parallel axis at this node
     */
    private static function axis(mixed $axis, int|string $key): mixed
    {
        return \is_array($axis) ? ($axis[$key] ?? null) : null;
    }
}
