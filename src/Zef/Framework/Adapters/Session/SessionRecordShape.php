<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;

/**
 * The session record's own object shape: the reading gate that
 * proves one decoded JSON document IS a session record -- exactly
 * the two vocabulary keys (`data`, `written_at`), each half
 * carrying its own kind (the bag a map, the written-at reading an
 * integer) -- the same question the durable delivery record's own
 * shape fold answers for its six keys ({@see
 * \Zef\Framework\Event\DeliveryRecordShape}), spelled here in the
 * session family's own words.
 *
 * The key gate is ONE judgement on purpose -- the count against
 * the vocabulary's own size, the two halves' presence -- because
 * every arm of it answers the same operator question ("this
 * document is not a two-key record") with the same remedy (fix
 * the record's keys), and the vocabulary's two keys are spelled in
 * one list the codec's writer and this gate share. The bag's own
 * kind and key domain is the values fold's question ({@see
 * SessionRecordValues}) -- the data half's map judgement included,
 * the two halves split the same way the delivery record's shape
 * and values folds always were: WHICH KEYS the record carries,
 * and WHAT the bag's map may be. A document that decodes on a
 * guess is a session that never was.
 */
final class SessionRecordShape
{
    /** The record's own key vocabulary: exactly the two keys, the
     * writer's spelling and this gate's judgement one list. */
    private const array RECORD_KEYS = ['data', 'written_at'];

    /**
     * Asserts one decoded document is the record it must be: the
     * two-key object shape with the written-at half an integer --
     * the data half's own kind is the values fold's judgement, not
     * this one's.
     *
     * @param mixed  $decoded the decoded JSON document
     * @param string $id      the record's own identifier, spelled into
     *                        every refusal
     *
     * @return array{data: mixed, writtenAt: int} the record's two raw halves
     *
     * @throws InvalidArgumentException when the document is not the
     *                                  object shape of one session record
     */
    public function assert(mixed $decoded, string $id): array
    {
        if (!\is_array($decoded)) {
            throw new InvalidArgumentException(
                "The session record '{$id}' must be a record map, "
                . get_debug_type($decoded) . ' given.',
            );
        }

        if (\count($decoded) !== \count(self::RECORD_KEYS) || !isset($decoded['data'], $decoded['written_at'])) {
            throw new InvalidArgumentException(
                "The session record '{$id}' must carry exactly the data and"
                . ' written_at keys.',
            );
        }

        $writtenAt = $decoded['written_at'];

        if (!\is_int($writtenAt)) {
            throw new InvalidArgumentException(
                "The session record '{$id}' written_at key must be an integer, "
                . get_debug_type($writtenAt) . ' given.',
            );
        }

        return ['data' => $decoded['data'], 'writtenAt' => $writtenAt];
    }
}
