<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;
use Random\RandomException;
use Zef\Framework\Event\DeliveryEntryId;
use Zef\Framework\Event\DrainTrace;
use Zef\Framework\Event\DrainTraceEncoder;

/**
 * The file-backed delivery store's drain journal: the `drains/`
 * subdirectory that holds the regime's traceability -- one
 * owner-only record per COMPLETED drain pass, named by a freshly
 * minted entry-style identity so two passes at the same now are
 * two records, never one overwritten. The record's spelling is
 * the Event lane's own encoder (the trace is the same JSON either
 * side of the store choice); the journal owns where it lives.
 *
 * A pass that did not complete writes no record -- an interrupted
 * pass is a crash window the at-least-once protocol owns, and a
 * half-pass's counts would be a story the drain never answered
 * with. The journal is append-only: nothing the regime does ever
 * rewrites or removes a trace, and reading the regime's history is
 * the operator's own question.
 */
final readonly class DeliveryDrainJournal
{
    /** The drain journal's directory name under the store root. */
    private const string DRAIN_LANE = 'drains';

    /** The extension every record file carries. */
    private const string EXTENSION = '.json';

    private DrainTraceEncoder $drainTraceEncoder;

    public function __construct(
        private string $directory,
        private DeliveryStaging $deliveryStaging,
    ) {
        $this->drainTraceEncoder = new DrainTraceEncoder();
    }

    /**
     * Appends one completed pass's trace to the journal -- the
     * five counts the pass answered with, at the drain's own now,
     * under a freshly minted record name.
     *
     * @throws InvalidArgumentException when the lane or its record
     *                                  cannot be written
     * @throws RandomException          when the engine's random source
     *                                  refuses to answer the record's name
     */
    public function recordDrainOutcome(DrainTrace $drainTrace): void
    {
        if (!is_dir($this->laneDirectory()) && !mkdir($this->laneDirectory(), 0o700)) {
            throw new InvalidArgumentException(
                'The delivery store cannot create its drain journal lane.',
            );
        }

        $this->deliveryStaging->write(
            $this->lanePath(DeliveryEntryId::mint()->spelling() . self::EXTENSION),
            $this->drainTraceEncoder->encode($drainTrace),
        );
    }

    /**
     * The journal's own directory under the store root.
     */
    private function laneDirectory(): string
    {
        return $this->directory . \DIRECTORY_SEPARATOR . self::DRAIN_LANE;
    }

    /**
     * The canonical address of one journal record.
     */
    private function lanePath(string $relative): string
    {
        return $this->directory . \DIRECTORY_SEPARATOR . self::DRAIN_LANE
            . \DIRECTORY_SEPARATOR . $relative;
    }
}
