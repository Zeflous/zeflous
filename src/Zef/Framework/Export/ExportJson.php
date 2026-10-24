<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use JsonException;

/**
 * The export family's JSON encoding: the one spelling every ecosystem
 * document renders with -- pretty-printed (these are artifacts an
 * operator reads, diffs, and commits), forward slashes unescaped
 * (route patterns and URLs are full of them), and throw-on-error so
 * an encoding failure surfaces as the export's own refusal instead of
 * a silent `false` -- the same flags the CLI inspector's JSON views
 * settled, spelled for the runtime tree this family owns.
 */
final readonly class ExportJson
{
    private const int FLAGS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR;

    /**
     * @param array<mixed> $document the export family's own data shape
     *
     * @return non-empty-string the encoded document
     *
     * @throws ExportException when the document cannot be encoded
     */
    public static function encode(array $document): string
    {
        try {
            $encoded = json_encode($document, self::FLAGS);
        } catch (JsonException $jsonException) {
            throw new ExportException(
                'The export document could not be encoded: ' . $jsonException->getMessage(),
                0,
                $jsonException,
            );
        }

        return $encoded;
    }
}
