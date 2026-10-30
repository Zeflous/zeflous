<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;
use JsonException;
use Zef\Framework\Event\SerializableValues;

/**
 * The session record's writing half: the JSON translation of one
 * identifier's bag and the written-at reading its sliding expiry
 * judges -- `{"data": {...}, "written_at": <int>}`, exactly the
 * two keys, nothing else riding.
 *
 * The bag's survivability is judged at this boundary by the one
 * JSON-round-trip walk the tree already spells ({@see
 * SerializableValues}, reached one-way the same spelling the
 * delivery store's directory fold reaches the storage layer's
 * document-root judgment): a bag value the JSON grammar cannot
 * carry -- an object, a resource, NAN, a string that is not valid
 * UTF-8 -- is refused at the save that would have written it,
 * naming the value's own path, never as an opaque encode failure
 * and never as a corruption the next load discovers. The depth
 * question is the depth fold's own ({@see SessionBagDepth}),
 * answered at the same boundary.
 *
 * The written-at reading rides INSIDE the record rather than
 * borrowing the file's own mtime: the expiry judgment belongs to
 * the one clock seam the suite can freeze ({@see FileSessionAdapter}
 * -- the same discipline the in-memory driver's injectable clock
 * spells), not to the filesystem's own clock the suite cannot.
 */
final class SessionRecordEncoder
{
    /**
     * The bag's nesting ceiling: the record the engine's own JSON
     * grammar reads back keeps its bag within this many levels, so
     * the write boundary refuses deeper -- the read boundary's own
     * ceiling, honoured at the write.
     */
    private const int DEPTH_CEILING = 510;

    /**
     * Encodes one identifier's bag and its written-at reading as
     * the record the load boundary reads back.
     *
     * @param array<string, mixed> $data      the bag the settlement persists
     * @param int                  $writtenAt the save's own clock reading
     *
     * @throws InvalidArgumentException when the bag carries a value the
     *                                  record cannot survive, or nests past the depth ceiling
     */
    public function encode(array $data, int $writtenAt): string
    {
        $this->refuseWhatCannotSurvive($data);
        new SessionBagDepth()->refuseDeeperThan($data, self::DEPTH_CEILING);

        try {
            return json_encode(
                ['data' => $data, 'written_at' => $writtenAt],
                \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException(
                'The session record cannot be encoded: ' . $jsonException->getMessage() . '.',
                0,
                $jsonException,
            );
        }
    }

    /**
     * The survivability gate: the first value the JSON round trip
     * cannot carry, refused by its own kind and path.
     *
     * @param array<string, mixed> $data the bag being written
     */
    private function refuseWhatCannotSurvive(array $data): void
    {
        $offense = SerializableValues::offense($data);

        if ($offense !== null) {
            throw new InvalidArgumentException(
                'The session record cannot carry ' . $offense . '.',
            );
        }
    }
}
