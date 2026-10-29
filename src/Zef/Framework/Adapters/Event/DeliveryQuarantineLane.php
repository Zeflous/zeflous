<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Zef\Framework\Event\ListenerIdentity;

/**
 * The file-backed delivery store's quarantine lane: the
 * `quarantine/` subdirectory that holds the durable re-spelling
 * of the listener quarantine -- one owner-only record per
 * QUARANTINED identity, keyed by the identity itself, so the
 * containment a previous process earned is the containment a
 * fresh process reads. The record's EXISTENCE is the book's own
 * answer: isListenerQuarantined asks nothing but the filesystem,
 * and the record's content is the operator's own story -- which
 * identity, and when the threshold crossed.
 *
 * The lane never lifts a quarantine on its own: restoring a
 * listener is the operator's judgment, spelled by removing the
 * record -- the same one-way posture the process-local book's own
 * doctrine spells, carried across the process boundary.
 */
final readonly class DeliveryQuarantineLane
{
    /** The quarantine lane's directory name under the store root. */
    private const string QUARANTINE_LANE = 'quarantine';

    /** The extension every record file carries. */
    private const string EXTENSION = '.json';

    public function __construct(
        private string $directory,
        private DeliveryStaging $deliveryStaging,
    ) {
    }

    /**
     * Records the containment act -- one record per quarantined
     * identity, so the same identity quarantined twice (the
     * at-least-once duplicate) rewrites the same record, never
     * doubles the book.
     *
     * @throws InvalidArgumentException when the lane or its record
     *                                  cannot be written
     */
    public function quarantineListener(string $listenerIdentity, int $quarantinedAt): void
    {
        $record = json_encode(
            [
                'listener_identity' => $listenerIdentity,
                'quarantined_at' => $quarantinedAt,
            ],
            \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION,
        );

        if (!is_dir($this->laneDirectory()) && !mkdir($this->laneDirectory(), 0o700)) {
            throw new InvalidArgumentException(
                'The delivery store cannot create its quarantine lane.',
            );
        }

        $this->deliveryStaging->write(
            $this->lanePath($listenerIdentity . self::EXTENSION),
            $record,
        );
    }

    /**
     * Whether the identity is quarantined -- the record's own
     * existence, the filter every future defer reads.
     */
    public function isListenerQuarantined(ListenerIdentity $listenerIdentity): bool
    {
        return is_file($this->lanePath($listenerIdentity->spelling() . self::EXTENSION));
    }

    /**
     * The lane's own directory under the store root.
     */
    private function laneDirectory(): string
    {
        return $this->directory . \DIRECTORY_SEPARATOR . self::QUARANTINE_LANE;
    }

    /**
     * The canonical address of one lane record.
     */
    private function lanePath(string $relative): string
    {
        return $this->directory . \DIRECTORY_SEPARATOR . self::QUARANTINE_LANE
            . \DIRECTORY_SEPARATOR . $relative;
    }
}
