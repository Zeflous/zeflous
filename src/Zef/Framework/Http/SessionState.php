<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Closure;

/**
 * The request-scoped session bag: one identifier, one data bag, and
 * the three flags the outbound boundary ({@see SessionMiddleware})
 * reads to decide what the storage and the `Set-Cookie` header owe --
 * freshly minted, dirtied by writes, or marked for destruction.
 *
 * The bag is mutable on purpose: it is the request's own state, the
 * one place in the HTTP family where mutation is the contract (the
 * request objects are immutable withers, the response is the
 * handler's, and the session is the counter the request carries
 * through the pipeline -- the attribute the middleware decorates the
 * request with, exactly as the observability lane decorates its span
 * onto the request). A controller pulls the bag through the
 * middleware's own attribute name, writes into it, and the boundary
 * persists what changed -- the host never touches the adapter, which
 * is the whole promise of the port.
 *
 * The dirty flag is write-honest: a write marks it, a removal marks
 * it only when the key was actually there (a no-op removal that
 * dirtied the bag would answer a redundant save the storage did not
 * need), and the raw bag's exposure ({@see self::data()}) cannot
 * dirty anything because PHP's arrays are copied on return -- a host
 * mutating the array it read mutates its own copy, never the
 * session's.
 *
 * Destruction ({@see self::destroy()}) empties the bag and marks it:
 * the boundary destroys the storage entry and answers the deletion
 * cookie. Regeneration ({@see self::regenerate()}) is the identifier
 * rotation a privilege change classically asks for (issue #801's
 * finding 6, the OWASP fixation window: an identifier minted
 * pre-login must not ride the post-login session), the data kept and
 * the identifier swapped: the boundary destroys the retired entry,
 * saves the bag under the fresh identifier, and answers the
 * issuance cookie carrying it.
 */
final class SessionState
{
    private bool $dirty = false;

    private bool $destroyed = false;

    private bool $regenerated = false;

    /** The identifier the rotation retired, null until one runs. */
    private ?string $retiredId = null;

    /**
     * @param string                   $id     the session identifier
     * @param array<string, mixed>     $data   the loaded bag, or empty for a
     *                                         freshly minted session
     * @param bool                     $isNew  whether the identifier was minted
     *                                         this request (no cookie the
     *                                         client carried matched a live
     *                                         session)
     * @param null|(Closure(): string) $mintId the identifier seam the
     *                                         rotation ({@see self::regenerate()})
     *                                         mints through; null leaves the
     *                                         state unrotatable (the
     *                                         boundary-owned states carry the
     *                                         resolution's own mint)
     */
    public function __construct(
        private string $id,
        private array $data,
        private readonly bool $isNew = false,
        private readonly ?Closure $mintId = null,
    ) {
    }

    /**
     * The session's identifier.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Whether the identifier was minted this request.
     */
    public function isNew(): bool
    {
        return $this->isNew;
    }

    /**
     * Whether the bag carries writes the storage has not seen.
     */
    public function isDirty(): bool
    {
        return $this->dirty;
    }

    /**
     * Whether the session was marked for destruction this request.
     */
    public function isDestroyed(): bool
    {
        return $this->destroyed;
    }

    /**
     * One key's value, or null when the bag does not carry it -- the
     * host's own judgement over the mixed the bag honestly holds (the
     * session's data is host data; the family's analysers never widen
     * it, exactly as the parsed body's is).
     */
    public function value(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * The raw bag, copied on return: mutating the array this answers
     * mutates the caller's copy, never the session's.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Writes one key's value, marking the bag dirty.
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->dirty = true;
    }

    /**
     * Removes one key, marking the bag dirty only when the key was
     * actually there -- a removal over an absent key is a no-op the
     * storage is never asked to persist.
     */
    public function remove(string $key): void
    {
        if (!\array_key_exists($key, $this->data)) {
            return;
        }

        unset($this->data[$key]);
        $this->dirty = true;
    }

    /**
     * Whether the session's identifier was rotated this request.
     */
    public function isRegenerated(): bool
    {
        return $this->regenerated;
    }

    /**
     * The identifier the rotation retired, null until one runs: the
     * boundary's own read, the entry the settlement destroys beside
     * the save under the fresh identifier.
     */
    public function retiredId(): ?string
    {
        return $this->retiredId;
    }

    /**
     * Marks the session for destruction and empties the bag: the
     * boundary destroys the storage entry and answers the deletion
     * cookie.
     */
    public function destroy(): void
    {
        $this->data = [];
        $this->destroyed = true;
    }

    /**
     * Rotates the session's identifier, keeping the bag the request
     * already wrote: the privilege-change spelling (issue #801's
     * finding 6, the OWASP fixation window -- the identifier minted
     * before the login must not ride the authenticated session after
     * it), the data deliberately KEPT (the rotation protects the
     * identifier's history, never erasing the session's own content
     * -- erasure is {@see self::destroy()}'s own verdict). The
     * boundary retires the prior identifier's storage entry and
     * re-issues the cookie carrying the fresh one; a destroyed
     * session has nothing left to rotate, an unrotatable state (no
     * mint at construction) refuses loudly rather than degrading
     * into the silent no-op a privilege change must never read, and
     * a second call is the idempotent spelling -- one rotation per
     * request, the boundary's save answering it once.
     */
    public function regenerate(): void
    {
        if ($this->destroyed || $this->regenerated) {
            return;
        }

        if (!$this->mintId instanceof Closure) {
            throw new HttpException(
                'The session identifier cannot rotate: the state was constructed without the '
                . 'identifier seam the rotation mints through (the boundary-owned states carry '
                . "the resolution's own mint -- construct the state through the session "
                . 'boundary, or hand the mint the rotation asks for).',
            );
        }

        $this->retiredId = $this->id;
        $this->id = ($this->mintId)();
        $this->regenerated = true;
        $this->dirty = true;
    }
}
