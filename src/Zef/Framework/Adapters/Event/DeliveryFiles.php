<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;

/**
 * The file-backed delivery store's on-disk layout: which file a
 * durable entry is -- one entry record per entry identity at the
 * store's root, owner-only through the write discipline
 * {@see DeliveryStaging} owns -- and the scan that answers the
 * store's own bookkeeping, skipping dot-files (staging orphans,
 * operator sweep material), the dead-letter lane and non-record
 * files. The dead-letter lane itself is {@see DeliveryDeadLane}'s
 * own question; this fold only names the entry files.
 */
final readonly class DeliveryFiles
{
    /** The extension every record file carries. */
    private const string EXTENSION = '.json';

    public function __construct(
        private string $directory,
        private DeliveryStaging $deliveryStaging = new DeliveryStaging(),
    ) {
    }

    /**
     * Writes one entry file under the entry's own name.
     *
     * @throws InvalidArgumentException when the entry file cannot
     *                                  be written
     */
    public function writeEntry(string $entryId, string $json): void
    {
        $this->deliveryStaging->write($this->pathOf($entryId . self::EXTENSION), $json);
    }

    /**
     * Removes one entry file -- idempotent: an entry already
     * removed is the settled state itself, never a refusal (the
     * at-least-once protocol's own no-op).
     *
     * @throws InvalidArgumentException when the entry file exists
     *                                  but cannot be removed
     */
    public function removeEntry(string $entryId): void
    {
        $destination = $this->pathOf($entryId . self::EXTENSION);

        if (!file_exists($destination)) {
            return;
        }

        if (!unlink($destination)) {
            throw new InvalidArgumentException(
                "The delivery store cannot remove the entry '{$entryId}'.",
            );
        }
    }

    /**
     * Reads one entry file the scan answered.
     *
     * @throws InvalidArgumentException when the entry file cannot
     *                                  be read
     */
    public function readEntry(string $filename): string
    {
        $json = file_get_contents($this->pathOf($filename));

        if ($json === false) {
            throw new InvalidArgumentException(
                "The delivery entry file '{$filename}' cannot be read.",
            );
        }

        return $json;
    }

    /**
     * The entry filenames the store's root carries -- dot-files
     * (staging orphans, operator sweep material) skipped, the
     * dead-letter lane skipped, non-record files skipped. The
     * order is the directory's own, the caller's to sort by the
     * records' own answers.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException when the store's root
     *                                  cannot be scanned
     */
    public function entryFilenames(): array
    {
        $scanned = scandir($this->directory);

        if ($scanned === false) {
            throw new InvalidArgumentException(
                'The delivery store directory cannot be scanned.',
            );
        }

        $filenames = [];

        foreach ($scanned as $filename) {
            if (str_starts_with($filename, '.') || !str_ends_with($filename, self::EXTENSION)) {
                continue;
            }

            if (!is_file($this->pathOf($filename))) {
                continue;
            }

            $filenames[] = $filename;
        }

        return $filenames;
    }

    /**
     * The canonical address of one record relative to the store's
     * resolved root.
     */
    private function pathOf(string $relative): string
    {
        return $this->directory . \DIRECTORY_SEPARATOR . $relative;
    }
}
