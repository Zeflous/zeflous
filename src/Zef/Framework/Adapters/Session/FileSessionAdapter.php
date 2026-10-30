<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use Closure;
use InvalidArgumentException;
use Override;
use Zef\Framework\Http\SessionAdapterInterface;

/**
 * The session port's file driver: the local filesystem's answer to
 * the storage question, over the same three verbs the in-memory
 * driver answers -- the driver a deployment spells with the
 * `http.session.store` knob's `file` word, whose honest scope is
 * every worker that can read the directory (the cross-worker
 * visibility the in-memory driver's own documentation says a
 * multi-worker deployment must not live without).
 *
 * Every record is one owner-only JSON file named by the session's
 * own identifier, the written-at reading riding INSIDE the record
 * (never the file's mtime: the expiry judgement belongs to the one
 * clock seam the suite can freeze, the same discipline the
 * in-memory driver's injectable clock spells). The write is the
 * one-rename discipline ({@see SessionRecordStaging}) over the
 * record's own encoder ({@see SessionRecordEncoder}) so a load
 * never observes a half-written bag; the read and its expiry
 * judgment are the load walk ({@see SessionRecordLoad}) over the
 * record's own decoder ({@see SessionRecordDecoder}); the
 * cadenced sweep that retires the abandoned is its own fold
 * ({@see SessionSweep}); and the directory's posture (existing,
 * writable, non-symlink, owner-only, outside the public document
 * root) is the store's own fold ({@see SessionStoreDirectory}) --
 * the session bags are the users' own data spelled in the clear,
 * and the driver refuses to write them anywhere a disclosure could
 * read them.
 *
 * The refusals this lane spells are construction-time
 * {@see InvalidArgumentException}s -- the adapters lane's own
 * posture (the durable delivery store's), the rule-17 construction
 * doctrine the storage layout's own decision record spells: a
 * misprovisioned store halts the composition that names it, never
 * a request path that would have to answer a half-initialised
 * session family.
 */
final readonly class FileSessionAdapter implements SessionAdapterInterface
{
    private SessionRecordFiles $sessionRecordFiles;

    private SessionRecordEncoder $sessionRecordEncoder;

    private SessionRecordStaging $sessionRecordStaging;

    private SessionRecordLoad $sessionRecordLoad;

    private SessionSweep $sessionSweep;

    /**
     * @param string                $directory   the store's root directory
     *                                           (validated by {@see SessionStoreDirectory})
     * @param int                   $idleTimeout the sliding expiry, in seconds
     *                                           (the policy's bounds hold the corridor)
     * @param null|(Closure(): int) $clock       the Unix-seconds clock,
     *                                           injectable so the suite can freeze it;
     *                                           defaults to the engine's own time()
     *
     * @throws InvalidArgumentException when the directory refuses the
     *                                  store's posture
     */
    public function __construct(
        string $directory,
        int $idleTimeout,
        private ?Closure $clock = null,
    ) {
        $resolvedDirectory = SessionStoreDirectory::resolve($directory);

        $sessionRecordDecoder = new SessionRecordDecoder();

        $this->sessionRecordFiles = new SessionRecordFiles($resolvedDirectory);
        $this->sessionRecordEncoder = new SessionRecordEncoder();
        $this->sessionRecordStaging = new SessionRecordStaging();
        $this->sessionRecordLoad = new SessionRecordLoad(
            $this->sessionRecordFiles,
            $sessionRecordDecoder,
            $idleTimeout,
        );
        $this->sessionSweep = new SessionSweep(
            new SessionRecordScan($resolvedDirectory),
            $this->sessionRecordFiles,
            $sessionRecordDecoder,
            $idleTimeout,
        );
    }

    #[Override]
    public function load(string $id): ?array
    {
        return $this->sessionRecordLoad->load($id, $this->now());
    }

    #[Override]
    public function save(string $id, array $data): void
    {
        $now = $this->now();

        $this->sessionRecordStaging->write(
            $this->sessionRecordFiles->recordPath($id),
            $this->sessionRecordEncoder->encode($data, $now),
        );

        $this->sessionSweep->rideSave($now);
    }

    #[Override]
    public function destroy(string $id): void
    {
        $this->sessionRecordFiles->removeRecord($id);
    }

    /**
     * The current reading of the injectable clock.
     */
    private function now(): int
    {
        return ($this->clock ?? time(...))();
    }
}
