<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use InvalidArgumentException;
use JsonException;

/**
 * The session record's reading half: the JSON translation of one
 * record back into the bag it spelled and the written-at reading
 * its expiry judges. The fold composes the reading boundary's own
 * gates -- the JSON decode itself (invalid JSON refused with the
 * engine's own reason, the record's identifier spelled) and the
 * record's shape and values folds ({@see SessionRecordShape},
 * {@see SessionRecordValues}) -- exactly the way the durable
 * delivery record's own decoder composes its shape and values
 * gates. A record that decodes on a guess is a session that never
 * was.
 */
final readonly class SessionRecordDecoder
{
    public function __construct(
        private SessionRecordShape $sessionRecordShape = new SessionRecordShape(),
        private SessionRecordValues $sessionRecordValues = new SessionRecordValues(),
    ) {
    }

    /**
     * Decodes one record back into the bag it spelled and the
     * written-at reading its expiry judges.
     *
     * @return array{data: array<string, mixed>, writtenAt: int} the
     *                                                           bag and its written-at reading
     *
     * @throws InvalidArgumentException when the record is not the
     *                                  JSON spelling of one session bag
     */
    public function decode(string $json, string $id): array
    {
        try {
            /** @var mixed $decoded the decoded record */
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new InvalidArgumentException(
                "The session record '{$id}' is not valid JSON: "
                . $jsonException->getMessage() . '.',
                0,
                $jsonException,
            );
        }

        $record = $this->sessionRecordShape->assert($decoded, $id);

        return [
            'data' => $this->sessionRecordValues->narrow($record['data'], $id),
            'writtenAt' => $record['writtenAt'],
        ];
    }
}
