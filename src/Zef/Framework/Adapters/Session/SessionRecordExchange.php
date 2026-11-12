<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use Closure;
use InvalidArgumentException;

/**
 * The session record's read-modify-write exchange: the port's three
 * verbs each armed with the lane's own span discipline -- the load
 * that TAKES the lane and answers the walk under it, releasing it on
 * the way out when the walk refuses; the save that ends the lane the
 * load began (or takes its own, when this save is the record's first
 * write), releasing it in the finally so a refused write never strands
 * it in this process; the destroy that retires the record and releases
 * the lane together -- a request that will never save also never
 * holds the lane its sibling waits on.
 *
 * The exchange is the fold the lane's arrival demanded ({@see
 * SessionRecordLock}, the issue #781 finding): before it, the port's
 * verbs composed the walk folds bare, and the adapter carried the
 * whole wiring in one class; the lane's span discipline -- acquire,
 * walk, release, and the refusal arms each verb's own exit carries --
 * is one question with one vocabulary, exactly where the archaeology
 * ceiling's own fold-split doctrine has always landed it ({@see
 * docs/ci-notes/session-file-storage.md}, KDD-SF9's own lineage).
 *
 * The walk folds themselves stay where they always were -- the read
 * and its expiry judgment ({@see SessionRecordLoad}), the staging
 * write ({@see SessionRecordStaging}, {@see SessionRecordEncoder}),
 * the retirement ({@see SessionRecordFiles}), the cadenced sweep
 * ({@see SessionSweep}) -- the exchange owns only the arming around
 * them, over the adapter's own injectable clock seam.
 */
final readonly class SessionRecordExchange
{
    /**
     * @param SessionRecordLock     $sessionRecordLock    the lane the span discipline reads
     * @param SessionRecordLoad     $sessionRecordLoad    the read walk the load arms
     * @param SessionRecordStaging  $sessionRecordStaging the atomic write the save arms
     * @param SessionRecordEncoder  $sessionRecordEncoder the record's own writing half
     * @param SessionRecordFiles    $sessionRecordFiles   the store's own layout fold
     * @param SessionSweep          $sessionSweep         the cadenced sweep riding the save
     * @param null|(Closure(): int) $clock                the Unix-seconds clock, injectable
     *                                                    so the suite can freeze it; defaults
     *                                                    to the engine's own time()
     */
    public function __construct(
        private SessionRecordLock $sessionRecordLock,
        private SessionRecordLoad $sessionRecordLoad,
        private SessionRecordStaging $sessionRecordStaging,
        private SessionRecordEncoder $sessionRecordEncoder,
        private SessionRecordFiles $sessionRecordFiles,
        private SessionSweep $sessionSweep,
        private ?Closure $clock = null,
    ) {
    }

    /**
     * One identifier's bag under the lane's own span: the lane taken
     * before the read, the walk answered under it, and a refused walk
     * releasing the lane on its way out -- a poisoned store stays
     * loud on every later load, never a lane the process silently
     * cannot take again.
     *
     * @return null|array<string, mixed> the stored bag, or null
     *
     * @throws InvalidArgumentException when the record cannot be
     *                                  read or does not decode
     */
    public function load(string $id): ?array
    {
        $this->sessionRecordLock->acquire($id);

        try {
            return $this->sessionRecordLoad->load($id, $this->now());
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->sessionRecordLock->release();

            throw $invalidArgumentException;
        }
    }

    /**
     * One identifier's bag written under the lane's own span: the
     * staging write and the sweep that rides it, the release riding
     * the finally -- the sibling request waiting on the lane is
     * answered whether this write lands or refuses.
     *
     * @param array<string, mixed> $data the bag the settlement persists
     *
     * @throws InvalidArgumentException when the record cannot be
     *                                  written, secured, renamed or scanned
     */
    public function save(string $id, array $data): void
    {
        $this->sessionRecordLock->acquire($id);

        try {
            $now = $this->now();

            $this->sessionRecordStaging->write(
                $this->sessionRecordFiles->recordPath($id),
                $this->sessionRecordEncoder->encode($data, $now),
            );

            $this->sessionSweep->rideSave($now);
        } finally {
            $this->sessionRecordLock->release();
        }
    }

    /**
     * One identifier's retirement under the lane's own span: the
     * record removed and the lane released together.
     */
    public function destroy(string $id): void
    {
        $this->sessionRecordLock->acquire($id);

        try {
            $this->sessionRecordFiles->removeRecord($id);
        } finally {
            $this->sessionRecordLock->release();
        }
    }

    /**
     * The current reading of the injectable clock.
     */
    private function now(): int
    {
        return ($this->clock ?? time(...))();
    }
}
