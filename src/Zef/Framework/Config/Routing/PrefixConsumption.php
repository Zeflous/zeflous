<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

/**
 * Consumes a node's static prefix from a request path.
 *
 * The arithmetic is character-based (`mb_*`), so multi-byte static text
 * is consumed exactly like ASCII text: the prefix must continue the
 * path at the offset, character for character.
 *
 * @internal
 */
final class PrefixConsumption
{
    /**
     * The character offset after the node's prefix, or null when the
     * path does not continue with that prefix.
     */
    public static function positionAfterPrefix(RadixNode $radixNode, string $path, int $offset): ?int
    {
        $prefix = $radixNode->prefix();

        if (mb_substr($path, $offset, mb_strlen($prefix)) !== $prefix) {
            return null;
        }

        return $offset + mb_strlen($prefix);
    }
}
