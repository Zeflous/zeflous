<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The replay family's store vocabulary: the closed set of seen-table
 * topologies the `security.auth.replay_store` knob may name.
 *
 * The reading mirrors the rate-limit family's own
 * {@see RateLimitStores} doctrine, because the topology statement is
 * the same statement: a replayed id that lands on a different worker
 * of a process tree is re-accepted by a per-process table -- the
 * configured protection is probabilistically absent, present only
 * with probability 1/N -- and the operator who declares `apcu` has
 * stated they want the table shared across the tree. An unknown name
 * is rejected with the value spelled out, never silently answered
 * with the per-process default: a shared-table request the operator
 * believes they made while their replay protection quietly stays
 * per-worker is exactly the silent topology lie the rate-limit
 * family's census refuses.
 */
final class ReplayStores
{
    /** The per-process seen-table ({@see BoundedReplayProtector}) -- the reference's own spelling. */
    public const string MEMORY = 'memory';

    /** The one-process-tree shared seen-table ({@see ApcuReplayProtector}). */
    public const string APCU = 'apcu';

    /**
     * Reads one replay-store knob's value through the closed vocabulary.
     *
     * @throws HttpException when the value is not one of the known stores
     */
    public static function read(mixed $value): string
    {
        if (\is_string($value)) {
            $folded = mb_strtolower($value);

            if (\in_array($folded, [self::MEMORY, self::APCU], true)) {
                return $folded;
            }

            throw new HttpException(
                'The security.auth replay_store knob must be one of the known replay stores '
                . "(memory, apcu), '" . $value . "' given.",
            );
        }

        throw new HttpException(
            'The security.auth replay_store knob must be one of the known replay stores '
            . '(memory, apcu), ' . get_debug_type($value) . ' given.',
        );
    }
}
