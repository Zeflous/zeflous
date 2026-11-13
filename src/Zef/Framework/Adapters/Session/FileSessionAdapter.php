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
 * read-modify-write lane held for the whole span between one
 * request's load and its save or destroy is the sidecar lock fold
 * ({@see SessionRecordLock}) -- the native `files` handler's own
 * semantics, the issue #781 finding's answer: two overlapping
 * requests on one session (two tabs, a double-submit, an AJAX
 * poller) serialized instead of interleaving last-write-wins, the
 * second request's read-modify-write beginning over the first
 * one's write -- the arming itself the exchange fold's own
 * question ({@see SessionRecordExchange}), every verb this driver
 * answers through it; the cadenced sweep that retires the
 * abandoned is its own fold ({@see SessionSweep}); and the
 * directory's posture (existing, writable, non-symlink, owner-only,
 * outside the public document root) is the store's own fold
 * ({@see SessionStoreDirectory}) -- the session bags are the
 * users' own data spelled in the clear, and the driver refuses to
 * write them anywhere a disclosure could read them.
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
    private SessionRecordExchange $sessionRecordExchange;

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
        ?Closure $clock = null,
    ) {
        $resolvedDirectory = SessionStoreDirectory::resolve($directory);

        $sessionRecordDecoder = new SessionRecordDecoder();
        $sessionRecordFiles = new SessionRecordFiles($resolvedDirectory);
        $sessionRecordLock = new SessionRecordLock($sessionRecordFiles);

        $this->sessionRecordExchange = new SessionRecordExchange(
            $sessionRecordLock,
            new SessionRecordLoad($sessionRecordFiles, $sessionRecordDecoder, $idleTimeout),
            new SessionRecordStaging(),
            new SessionRecordEncoder(),
            $sessionRecordFiles,
            new SessionSweep(
                new SessionRecordScan($resolvedDirectory),
                $sessionRecordFiles,
                $sessionRecordDecoder,
                $idleTimeout,
            ),
            $clock,
        );
    }

    #[Override]
    public function load(string $id): ?array
    {
        return $this->sessionRecordExchange->load($id);
    }

    #[Override]
    public function save(string $id, array $data): void
    {
        $this->sessionRecordExchange->save($id, $data);
    }

    #[Override]
    public function destroy(string $id): void
    {
        $this->sessionRecordExchange->destroy($id);
    }
}
