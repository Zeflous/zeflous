<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Normalises the SAPI upload specification into the PSR-7 upload tree.
 *
 * `$_FILES` is a parallel-array specification, not a tree of objects: a
 * multi-file field carries each of name/type/tmp_name/error/size as an
 * array aligned by key, nesting arbitrarily deep. This collaborator
 * answers the leaf tree {@see UploadedTree} validates: the top level
 * walks the specification fields and every spec-shaped entry -- an
 * array carrying the `error` axis -- is overwritten in place with its
 * walked tree ({@see UploadWalk}), so a specification without an error
 * axis is not a SAPI upload entry and passes through verbatim. The leaf
 * building itself lives in {@see UploadLeaf}.
 */
final class ServerUploads
{
    /**
     * Normalises a SAPI upload specification into the PSR-7 upload tree.
     *
     * @param array<int|string, mixed> $files the upload specification, typically $_FILES
     *
     * @return array<int|string, mixed> the tree whose leaves are UploadedFileInterface instances
     */
    public static function normalize(array $files): array
    {
        $normalized = $files;

        foreach ($files as $field => $spec) {
            if (!\is_array($spec) || !\array_key_exists('error', $spec)) {
                continue;
            }

            $normalized[$field] = UploadNode::walk(
                $spec['name'] ?? null,
                $spec['type'] ?? null,
                $spec['tmp_name'] ?? null,
                $spec['error'],
                $spec['size'] ?? null,
            );
        }

        return $normalized;
    }
}
