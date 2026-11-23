<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Temp;

use Closure;
use InvalidArgumentException;

/**
 * The temp lane's writer: the landing engine that puts a process's
 * scratch inside the audited `temp` lane -- the reservation the
 * layout carried from its first day (KDD-SL3's census-moment
 * doctrine allowed it because the reservation was the roadmap's
 * own recorded open slice, the priorities table's own P1 row)
 * closed by the pull request that lands this class, with the
 * cadenced sweep the row's own remaining clause spells.
 *
 * THE ENGINE OWNS THE LANDING'S WHOLE GRAMMAR, the host never
 * spells a byte of it: the landed name is the writer's own
 * random draw (sixteen random bytes spelled as thirty-two
 * hexadecimal characters, extension-less by construction -- the
 * same discipline the uploads lane spells, no host spelling ever
 * reaching a filesystem call), the write rides the one-rename
 * staging discipline ({@see TempStaging} -- a later read never
 * observes a half-written scratch), and the cadenced sweep rides
 * the write itself ({@see TempSweep} -- the write lands first,
 * then the sweep rides it, the session lane's own ordering: a
 * sweep that takes its files never touches the write that paid
 * for it).
 *
 * The clock is the store's own injectable seam ({@see
 * \Zef\Framework\Adapters\Session\SessionRecordExchange} the
 * lineage): the sweep's judgment needs one now-reading, and the
 * suite freezes it -- the default is the engine's own time().
 *
 * The constructor's own bounds are the Storage posture the
 * sister writers carry: the directory resolves through the lane's
 * own posture fold ({@see TempStoreDirectory}), and the
 * retention's own bound lives here -- at least one second, the
 * retention the knob's integer seam ({@see
 * \Zef\Framework\TempRetention}) already guaranteed at the
 * declaration, guarded again at the composition the same way the
 * uploads admission guards its ceiling.
 *
 * @api the host's entry point: the scratch-facing surface the
 *      `temp.store` registration answers.
 */
final readonly class FileTempStore
{
    /**
     * The landed name's own entropy: sixteen random bytes spelled
     * as thirty-two hexadecimal characters, extension-less by
     * construction -- the lane's names carry no host spelling at
     * all, the same discipline the uploads lane spells for its
     * landed names.
     */
    private const int NAME_BYTES = 16;

    /**
     * The temp lane's resolved canonical address.
     */
    private string $directory;

    /**
     * The cadenced sweep riding every write the store lands.
     */
    private TempSweep $tempSweep;

    /**
     * @param string                $directory  the temp lane's root directory
     *                                          (validated by {@see TempStoreDirectory}:
     *                                          existing, writable, non-symlink, owner-only,
     *                                          outside the public document root)
     * @param int                   $ttlSeconds the retention (>= 1): every scratch
     *                                          file whose age fully elapses it is
     *                                          retired by the cadenced sweep riding a
     *                                          later write
     * @param null|(Closure(): int) $clock      the Unix-seconds clock, injectable
     *                                          so the suite can freeze it; defaults to
     *                                          the engine's own time()
     *
     * @throws InvalidArgumentException when the retention is below
     *                                  one second, or the directory
     *                                  refuses the posture
     */
    public function __construct(
        string $directory,
        int $ttlSeconds,
        private ?Closure $clock = null,
    ) {
        if ($ttlSeconds < 1) {
            throw new InvalidArgumentException(
                'The temp store retention must be at least one second.',
            );
        }

        $this->directory = TempStoreDirectory::resolve($directory);
        $this->tempSweep = new TempSweep(
            new TempSweepCadence($this->directory),
            new TempScratchScan($this->directory),
            new TempRetirement($this->directory, $ttlSeconds),
        );
    }

    /**
     * Lands the scratch inside the temp lane under the writer's
     * own name, then the sweep rides the write.
     *
     * @param string $payload the scratch bytes the host hands the lane
     *
     * @return non-empty-string the landed file's absolute path
     *
     * @throws InvalidArgumentException when the scratch cannot be
     *                                  written, secured or renamed into place, or the
     *                                  sweep's walk refuses
     */
    public function write(string $payload): string
    {
        $path = $this->directory . \DIRECTORY_SEPARATOR . bin2hex(random_bytes(self::NAME_BYTES));

        TempStaging::write($path, $payload);

        $this->tempSweep->rideWrite($this->now());

        return $path;
    }

    /**
     * The current reading of the injectable clock.
     */
    private function now(): int
    {
        return ($this->clock ?? time(...))();
    }
}
