<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\UploadedFileInterface;

/**
 * The uploaded-files tree guard: the PSR-7 upload tree is a nested array
 * whose leaves must be {@see UploadedFileInterface} instances -- a
 * normalized superglobal shape, not raw PHP upload arrays -- and any
 * other leaf is rejected fail-closed with the layer's exception,
 * byte-identical to the reference implementation (legacy
 * `zef-framework` `ServerRequest::assertUploadedTree`). Pure static
 * helper; the recursive walk visits every leaf of every nesting level
 * through the same single policy point.
 */
final class UploadedTree
{
    /**
     * @param array<array-key, mixed> $tree the candidate upload tree
     *
     * @throws HttpException when a leaf is not an UploadedFileInterface
     *                       instance
     */
    public static function assert(array $tree): void
    {
        array_walk_recursive($tree, static function (mixed $leaf): void {
            if (!$leaf instanceof UploadedFileInterface) {
                throw new HttpException('Uploaded files must contain only UploadedFileInterface leaves.');
            }
        });
    }
}
