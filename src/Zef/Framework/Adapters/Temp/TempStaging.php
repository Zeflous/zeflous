<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Temp;

use InvalidArgumentException;

/**
 * The temp store's write discipline: how one scratch file becomes
 * a file at all -- staged beside its destination under a
 * leading-dot name, secured owner-only, then renamed into place
 * with one {@see rename()}, the same discipline the session record
 * writes, the delivery store's records and the uploads lane's
 * landed moves already spell, so a later read never observes a
 * half-written scratch. The staging name carries a leading dot on
 * purpose: a process that dies between staging and rename strands
 * a dot-file the sweep's own scan grammar ignores (operator sweep
 * material, never a partial scratch a later write would have to
 * refuse).
 *
 * THE BYTE-COUNT IDENTITY IS THE PAYLOAD'S OWN BYTES: the written
 * count the write laid down against `mb_strlen()`'s BYTE count
 * under the `'8bit'` encoding -- the coding standard's own
 * byte-semantics idiom ({@see \Zef\Framework\Database\SqlIdentifier}
 * spells "the '8bit' encoding IS byte semantics"), because a
 * scratch payload is ARBITRARY host bytes, never the
 * ASCII-by-construction records the session and delivery lanes
 * encode (issue #788, finding 6's own lineage: those lanes'
 * plain `mb_strlen` pairing rides the invariant their encoders
 * escape every multibyte byte away, verified by their suites -- a
 * pairing that on THIS lane would count every multibyte
 * payload's CODEPOINTS against its written BYTES, refusing the
 * write that succeeded, the silent hole the finding's own
 * spelling refuses).
 */
final class TempStaging
{
    /**
     * Writes one scratch file atomically into its destination.
     *
     * @param string $destination the landed name the writer drew
     * @param string $payload     the scratch bytes the host handed the lane
     *
     * @throws InvalidArgumentException when the scratch cannot be
     *                                  written, secured or renamed into place
     */
    public static function write(string $destination, string $payload): void
    {
        $scratch = basename($destination);
        $staging = \dirname($destination) . \DIRECTORY_SEPARATOR . '.' . $scratch;

        if (file_put_contents($staging, $payload, \LOCK_EX) !== mb_strlen($payload, '8bit')) {
            throw new InvalidArgumentException(
                "The temp file store cannot write the scratch '{$scratch}'.",
            );
        }

        if (!chmod($staging, 0o600)) {
            throw new InvalidArgumentException(
                "The temp file store cannot secure the scratch '{$scratch}'.",
            );
        }

        if (!rename($staging, $destination)) {
            throw new InvalidArgumentException(
                "The temp file store cannot finalise the scratch '{$scratch}'.",
            );
        }
    }
}
