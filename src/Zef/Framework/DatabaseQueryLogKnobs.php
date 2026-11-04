<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\ConfigException;
use Zef\Framework\Database\QueryLogException;
use Zef\Framework\Database\QueryLogPolicy;

/**
 * The `database.query_log` block's own strict reading: the per-query
 * logging family's two knobs read into the family's pure policy
 * object -- the lineage the connections map's own {@see
 * DatabaseConnectionKnobs} spelled for one connection's word keys,
 * over the observability family the slow-request lane established.
 *
 * The ABSENT block is the family's silent posture (a null policy, no
 * emission object at all); a spelled block arms the family with its
 * own defaults (the arm ON within a spelled block, the default bar at
 * a tenth of the slow-request family's one-second weight). Every knob
 * is read strictly -- a native boolean, a native integer, and every
 * other type a named halt with the knob spelled -- because a
 * silently-mistyped arm is a protection the operator believes is on
 * while it is not. The block's own sub-map census rejects the unknown
 * key with the known list spelled out, the same guard walk the family
 * map's own {@see DatabaseKnobs} runs one level up. The corridor the
 * threshold must hold is the policy's own construction to assert, so
 * the config-driven read and the hand-spelled construction halt
 * identically ({@see QueryLogBounds}).
 */
final class DatabaseQueryLogKnobs
{
    /**
     * The children the `database.query_log` block owns, in declaration
     * order -- the one list the block's own sub-map census and its
     * strict reading share.
     */
    private const array KNOBS = ['enabled', 'threshold_ms'];

    /**
     * Reads the query-log block into the family's policy object, or
     * the silent posture's null.
     *
     * @param mixed $queryLog the block as the frozen repository carries
     *                        it: a knob map, or null when the block is
     *                        absent
     *
     * @return null|QueryLogPolicy the family's own knob set, or null
     *                             when the block stayed silent
     *
     * @throws ConfigException   when the block is neither a map nor
     *                           absent, carries an unknown knob, or a
     *                           knob's value refuses its strict reading
     * @throws QueryLogException when the threshold sits outside the
     *                           corridor the policy's construction asserts
     */
    public static function read(mixed $queryLog): ?QueryLogPolicy
    {
        if ($queryLog === null) {
            return null;
        }

        if (!\is_array($queryLog)) {
            throw new ConfigException(
                'The database.query_log configuration must be a map, ' . get_debug_type($queryLog) . ' given.',
            );
        }

        self::rejectUnknown($queryLog);

        $enabled = $queryLog['enabled'] ?? true;

        if (!\is_bool($enabled)) {
            throw new ConfigException('database.query_log enabled must be a boolean.');
        }

        $thresholdMs = $queryLog['threshold_ms'] ?? 100;

        if (!\is_int($thresholdMs)) {
            throw new ConfigException('database.query_log threshold_ms must be an integer.');
        }

        return new QueryLogPolicy($enabled, $thresholdMs);
    }

    /**
     * Rejects the block's unknown keys with the known list spelled
     * out.
     *
     * @param array<array-key, mixed> $map the query-log block's own map
     *
     * @throws ConfigException when the block carries an unknown knob
     */
    private static function rejectUnknown(array $map): void
    {
        $spelled = implode(', ', self::KNOBS);

        foreach (array_keys($map) as $knob) {
            if (\is_string($knob) && \in_array($knob, self::KNOBS, true)) {
                continue;
            }

            throw new ConfigException(
                "The database.query_log configuration carries the unknown knob '"
                . (\is_string($knob) ? $knob : get_debug_type($knob))
                . "' (known knobs: {$spelled}).",
            );
        }
    }
}
