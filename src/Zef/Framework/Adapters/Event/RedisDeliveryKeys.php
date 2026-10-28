<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use InvalidArgumentException;

/**
 * The Redis-backed delivery store's key vocabulary: one prefix --
 * the store's own namespace inside a server the deployment shares
 * with everything else it runs -- and the four key spellings the
 * whole store hangs from it: the entry record keyed by the entry's
 * own identity, the pending index (the sorted set whose members ARE
 * the entries and whose scores ARE their ready-at moments), the
 * claim key the exclusivity arms with its own expiry, and the dead
 * record keyed by the spent entry's identity. The grammar is the
 * operator's: printable ASCII without spaces, one to one hundred
 * twenty-eight characters -- a prefix an operator can type at the
 * server's own console, because the operator is the one who will.
 */
final readonly class RedisDeliveryKeys
{
    private const string PREFIX_PATTERN = '/^[\x21-\x7e]{1,128}$/';

    private string $prefix;

    /**
     * @param string $prefix the store's key namespace
     *
     * @throws InvalidArgumentException when the prefix is not one to
     *                                  one hundred twenty-eight printable ASCII characters
     *                                  without spaces
     */
    public function __construct(string $prefix)
    {
        if (preg_match(self::PREFIX_PATTERN, $prefix) !== 1) {
            throw new InvalidArgumentException(
                "A Redis delivery store's key prefix must be one to one hundred"
                . ' twenty-eight printable ASCII characters without spaces, got'
                . " '{$prefix}'.",
            );
        }

        $this->prefix = $prefix;
    }

    /**
     * The pending index -- the sorted set whose members are the entry
     * identities and whose scores are their ready-at moments, the
     * index the file adapter's own honesty ledger names as the
     * future adapters' business.
     */
    public function pendingKey(): string
    {
        return $this->prefix . ':pending';
    }

    /**
     * One entry's record key -- the JSON spelling the encoder owns,
     * keyed by the entry's own identity.
     */
    public function entryKey(string $entryId): string
    {
        return $this->prefix . ':entry:' . $entryId;
    }

    /**
     * One entry's claim key -- the exclusivity the cross-process
     * answer arms, carrying its own expiry, keyed by the entry's own
     * identity.
     */
    public function claimKey(string $entryId): string
    {
        return $this->prefix . ':claim:' . $entryId;
    }

    /**
     * One spent entry's dead record key -- the operator's lane, keyed
     * by the spent entry's own identity so the same story rewritten
     * is the same record replaced.
     */
    public function deadKey(string $entryId): string
    {
        return $this->prefix . ':dead:' . $entryId;
    }
}
