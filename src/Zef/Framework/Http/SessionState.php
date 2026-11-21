<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Closure;

/**
 * The request-scoped session bag: one identifier, one data bag, and
 * the flags the outbound boundary ({@see SessionMiddleware})
 * reads to decide what the storage and the `Set-Cookie` header owe
 * -- freshly minted, dirtied by writes, or marked for destruction.
 * The bag itself is {@see SessionDataBag}'s own unit and the
 * identifier's rotation is {@see SessionRotation}'s (the #801
 * round's own archeology answer: the state's difficulty crossed the
 * bar with the rotation aboard, and both halves of the class's own
 * docblock decomposition -- one identifier, one data bag -- moved
 * out), the state keeping the identity, its own flags, and the
 * composition alone.
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
 * issuance cookie carrying it. The rotation's own state -- whether
 * one ran, and which identifier it retired -- lives in its own unit
 * ({@see SessionRotation}), the bag in its own ({@see
 * SessionDataBag}), the state keeping the identifier, its own
 * flags, and the composition alone.
 */
final class SessionState
{
    private bool $destroyed = false;

    private readonly SessionRotation $sessionRotation;

    private readonly SessionDataBag $sessionDataBag;

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
        array $data,
        private readonly bool $isNew = false,
        ?Closure $mintId = null,
    ) {
        $this->sessionDataBag = new SessionDataBag($data);
        $this->sessionRotation = new SessionRotation($mintId);
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
        return $this->sessionDataBag->isDirty();
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
        return $this->sessionDataBag->value($key);
    }

    /**
     * The raw bag, copied on return: mutating the array this answers
     * mutates the caller's copy, never the session's.
     *
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->sessionDataBag->data();
    }

    /**
     * Writes one key's value, marking the bag dirty.
     */
    public function set(string $key, mixed $value): void
    {
        $this->sessionDataBag->set($key, $value);
    }

    /**
     * Removes one key, marking the bag dirty only when the key was
     * actually there -- a removal over an absent key is a no-op the
     * storage is never asked to persist.
     */
    public function remove(string $key): void
    {
        $this->sessionDataBag->remove($key);
    }

    /**
     * Whether the session's identifier was rotated this request.
     */
    public function isRegenerated(): bool
    {
        return $this->sessionRotation->rotated();
    }

    /**
     * The identifier the rotation retired, null until one runs: the
     * boundary's own read, the entry the settlement destroys beside
     * the save under the fresh identifier.
     */
    public function retiredId(): ?string
    {
        return $this->sessionRotation->retiredId();
    }

    /**
     * Marks the session for destruction and empties the bag: the
     * boundary destroys the storage entry and answers the deletion
     * cookie.
     */
    public function destroy(): void
    {
        $this->sessionDataBag->clear();
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
     * session has nothing left to rotate, the rotation itself
     * ({@see SessionRotation::rotate()}) refusing loudly when the
     * state was constructed without the mint, and answering the
     * identifier it was handed when one already ran -- one rotation
     * per request, the boundary's save answering it once.
     */
    public function regenerate(): void
    {
        if ($this->destroyed) {
            return;
        }

        $this->id = $this->sessionRotation->rotate($this->id);
        $this->sessionDataBag->markDirty();
    }
}
