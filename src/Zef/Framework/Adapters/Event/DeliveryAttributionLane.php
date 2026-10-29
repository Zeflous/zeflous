<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;

/**
 * The file-backed delivery store's attribution lane: the
 * `attribution/` subdirectory that holds the durable re-spelling
 * of the dead-letter book -- one owner-only record per ATTRIBUTED
 * death, keyed by the spent entry's own identity, so the
 * at-least-once redelivery of a crashed pass re-attributes the
 * SAME entry instead of doubling the book. The lane owns WHERE
 * the records live and HOW they are written: the record's own
 * spelling is the encoder's question ({@see DeliveryAttributionEncoder}),
 * what a written record spells is the decoder's
 * ({@see DeliveryAttributionDecoder}), and the count the threshold
 * reads is the scan's ({@see DeliveryAttributionScan}) -- the
 * entry book's own family geometry, replayed over the books' lane.
 */
final readonly class DeliveryAttributionLane
{
    /** The attribution lane's directory name under the store root. */
    private const string ATTRIBUTION_LANE = 'attribution';

    /** The extension every record file carries. */
    private const string EXTENSION = '.json';

    private DeliveryAttributionEncoder $deliveryAttributionEncoder;

    public function __construct(
        private string $directory,
        private DeliveryStaging $deliveryStaging,
    ) {
        $this->deliveryAttributionEncoder = new DeliveryAttributionEncoder();
    }

    /**
     * Writes one attributed-death record into the lane, keyed by
     * the spent entry's own identity -- the same entry attributed
     * twice (the at-least-once duplicate) rewrites the same
     * record, never doubles it.
     *
     * @throws InvalidArgumentException when the lane or its record
     *                                  cannot be written
     */
    public function recordAttributedDeath(
        string $entryId,
        string $listenerIdentity,
        int $attributedAt,
    ): void {
        if (!is_dir($this->laneDirectory()) && !mkdir($this->laneDirectory(), 0o700)) {
            throw new InvalidArgumentException(
                'The delivery store cannot create its attribution lane.',
            );
        }

        $this->deliveryStaging->write(
            $this->lanePath($entryId . self::EXTENSION),
            $this->deliveryAttributionEncoder->encode($entryId, $listenerIdentity, $attributedAt),
        );
    }

    /**
     * The record filenames the lane carries -- dot-files (staging
     * orphans, operator sweep material) skipped, non-record files
     * skipped, the directory's own order.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException when the lane cannot be scanned
     */
    public function attributionFilenames(): array
    {
        if (!is_dir($this->laneDirectory())) {
            return [];
        }

        $scanned = scandir($this->laneDirectory());

        if ($scanned === false) {
            throw new InvalidArgumentException(
                'The delivery store cannot scan its attribution lane.',
            );
        }

        $filenames = [];

        foreach ($scanned as $filename) {
            if (str_starts_with($filename, '.') || !str_ends_with($filename, self::EXTENSION)) {
                continue;
            }

            if (!is_file($this->lanePath($filename))) {
                continue;
            }

            $filenames[] = $filename;
        }

        return $filenames;
    }

    /**
     * Reads one attribution record's bytes -- the lane owns where
     * the record lives, the decoder owns what it spells.
     *
     * @throws InvalidArgumentException when the record file cannot
     *                                  be read
     */
    public function readAttribution(string $filename): string
    {
        $json = file_get_contents($this->lanePath($filename));

        if ($json === false) {
            throw new InvalidArgumentException(
                "The attribution record '{$filename}' cannot be read.",
            );
        }

        return $json;
    }

    /**
     * The lane's own directory under the store root -- created
     * owner-only on first use, the posture the store's own root
     * demands of every lane it owns.
     */
    private function laneDirectory(): string
    {
        return $this->directory . \DIRECTORY_SEPARATOR . self::ATTRIBUTION_LANE;
    }

    /**
     * The canonical address of one lane record.
     */
    private function lanePath(string $relative): string
    {
        return $this->directory . \DIRECTORY_SEPARATOR . self::ATTRIBUTION_LANE
            . \DIRECTORY_SEPARATOR . $relative;
    }
}
