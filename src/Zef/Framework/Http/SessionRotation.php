<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Closure;

/**
 * The session identifier's rotation state: the privilege-change seam
 * ({@see SessionState::regenerate()}) lifted into its own unit (the
 * #801 round's own archeology answer -- the state's difficulty grew
 * past the bar with the rotation aboard, and the rotation is the
 * cohesive piece that moves out, the state keeping the bag and its
 * own flags alone).
 *
 * The unit is the one mutable keeper of the rotation's own two facts
 * -- whether one ran, and which identifier it retired -- and the one
 * seam the fresh identifier is minted through: an unrotatable
 * construction (no mint handed in) refuses loudly rather than
 * degrading into the silent no-op a privilege change must never read
 * (issue #801's finding 6's own doctrine), a rotation that already
 * ran answers the identifier it was handed back (the idempotent
 * spelling -- one rotation per request, the boundary's save answering
 * it once), and the retired identifier is the entry the settlement
 * ({@see SessionSettlement}) destroys beside the save under the fresh
 * one.
 */
final class SessionRotation
{
    private bool $rotated = false;

    private ?string $retiredId = null;

    /**
     * @param null|(Closure(): string) $mintId the identifier seam the
     *                                         rotation mints through; null
     *                                         leaves the unit unrotatable
     *                                         (the boundary-owned states
     *                                         carry the resolution's own
     *                                         mint)
     */
    public function __construct(private readonly ?Closure $mintId)
    {
    }

    /**
     * Whether the identifier was rotated this request.
     */
    public function rotated(): bool
    {
        return $this->rotated;
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
     * Rotates: retires the current identifier and answers the fresh
     * one minted through the construction's own seam.
     *
     * A rotation that already ran answers the identifier it was
     * handed -- the idempotent spelling, one rotation per request --
     * and an unrotatable unit (no mint at construction) refuses
     * loudly rather than degrading into the silent no-op a privilege
     * change must never read.
     */
    public function rotate(string $currentId): string
    {
        if ($this->rotated) {
            return $currentId;
        }

        if (!$this->mintId instanceof Closure) {
            throw new HttpException(
                'The session identifier cannot rotate: the state was constructed without the '
                . 'identifier seam the rotation mints through (the boundary-owned states carry '
                . "the resolution's own mint -- construct the state through the session "
                . 'boundary, or hand the mint the rotation asks for).',
            );
        }

        $this->retiredId = $currentId;
        $this->rotated = true;

        return ($this->mintId)();
    }
}
