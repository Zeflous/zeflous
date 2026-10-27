<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;

/**
 * The file-backed delivery store's dead-letter lane: the `dead/`
 * subdirectory an operator reads -- created owner-only on first
 * use (the posture the store's own directory fold demands of the
 * root, carried into the lane the root owns), and keyed by the
 * spent entry's own identity, so the same story rewritten is the
 * same file replaced, never doubled. The record's spelling is the
 * encoder's own question; the lane owns where it lives.
 */
final readonly class DeliveryDeadLane
{
    /** The dead-letter lane's directory name under the store root. */
    private const string DEAD_LANE = 'dead';

    /** The extension every record file carries. */
    private const string EXTENSION = '.json';

    public function __construct(
        private string $directory,
        private DeliveryStaging $deliveryStaging,
    ) {
    }

    /**
     * Writes one dead-letter record into the lane, keyed by the
     * spent entry's own identity.
     *
     * @throws InvalidArgumentException when the lane or its record
     *                                  cannot be written
     */
    public function writeRecord(string $entryId, string $json): void
    {
        $deadLane = $this->directory . \DIRECTORY_SEPARATOR . self::DEAD_LANE;

        if (!is_dir($deadLane) && !mkdir($deadLane, 0o700)) {
            throw new InvalidArgumentException(
                'The delivery store cannot create its dead-letter lane.',
            );
        }

        $this->deliveryStaging->write(
            $deadLane . \DIRECTORY_SEPARATOR . $entryId . self::EXTENSION,
            $json,
        );
    }
}
