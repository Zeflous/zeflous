<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Storage\StorageLane;
use Zef\Framework\Storage\StorageLayout;
use Zef\Framework\Storage\StoragePaths;

/**
 * The error family's wiring onto the `files/**` route map: the fold
 * that answers the family's on-disk addresses from the frozen
 * configuration -- the one structured log address the error,
 * slow-request and process-boundary lanes share, and the replay
 * directory the snapshots land in -- each derived from the storage
 * layout when the host declared a root and left the knob empty
 * ({@see StorageLayout}, the integration the layout's own decision
 * record named as its first consumer).
 *
 * The precedence is deliberate and one-way: an explicitly spelled
 * address always wins, and derivation only fills the knob the host
 * left empty -- the storage root provisions the deployment's
 * defaults, never an override of the deployment's own words. The two
 * verbs answer the two questions their call sites already ask:
 * {@see self::logAddress()} answers the EFFECTIVE address (the three
 * factories' single seam -- an explicit `http.error_handling.log_path`
 * verbatim, the `logs` lane joined with the family's canonical file
 * name, or the empty no-sink posture), while {@see
 * self::replayDirectory()} answers the layout's OFFER (the `replay`
 * lane's address) and leaves the explicit-knob precedence to the
 * replay lane's own grammar fold ({@see ErrorReplayWiring}), which
 * already reads and validates the knob it owns.
 *
 * The root itself is read by the declaration fold ({@see
 * StorageRootDeclaration}) -- the strict census of the one `root`
 * knob, refusing a `storage.rooot` typo with the known list spelled
 * rather than letting it fall through to the ad-hoc posture
 * unnoticed -- and the walk is the layout's own over the WHOLE
 * five-lane tree, fail-closed with the refusing seam named: the error
 * family answers to the same map the inspector prints, not a
 * two-lane mini-walk that could drift from it. The privacy posture
 * stays where it already lives -- the JSON log destination's own file
 * checks, the replay directory's own owner-only requirement --
 * because this fold answers WHERE, never HOW PRIVATE (two questions,
 * two seams, the route map's own boundary).
 */
final class ErrorStorageWiring
{
    /**
     * The error family's canonical artifact name inside the `logs`
     * lane: the one spelling of the structured log file the runtime
     * writes and the CLI inspector reads by default.
     */
    public const string LOG_FILE_NAME = 'zef-errors.jsonl';

    /**
     * The deployment's one structured log address: the explicitly
     * spelled `http.error_handling.log_path` verbatim when the host
     * named one, the storage layout's `logs` lane joined with the
     * family's canonical file name when the host declared a root and
     * left the knob empty, and the empty string when neither is
     * declared (the no-sink posture the SAPI error log owns).
     *
     * @throws ConfigException when the log or storage knob is malformed
     */
    public static function logAddress(Config $config): string
    {
        $logPath = $config->get('http.error_handling.log_path', '');
        if (!\is_string($logPath)) {
            throw new ConfigException('http.error_handling.log_path must be a string.');
        }

        if ($logPath !== '') {
            return $logPath;
        }

        $paths = self::paths($config);
        if (!$paths instanceof StoragePaths) {
            return '';
        }

        return $paths->path(StorageLane::Logs) . \DIRECTORY_SEPARATOR . self::LOG_FILE_NAME;
    }

    /**
     * The replay lane's offer: the storage layout's `replay` lane
     * address when the host declared a root -- the default the
     * `http.error_handling.replay_path` knob falls back to when its
     * own grammar fold finds it empty -- and the empty string when no
     * root is declared, so the caller's own combination refusal keeps
     * answering the no-path case exactly as it did before the layout
     * existed.
     *
     * @throws ConfigException when the storage map is malformed or
     *                         carries an unknown knob
     */
    public static function replayDirectory(Config $config): string
    {
        $paths = self::paths($config);
        if (!$paths instanceof StoragePaths) {
            return '';
        }

        return $paths->path(StorageLane::Replay);
    }

    /**
     * The walked layout over the declared root, or null when the
     * declaration answers silent (no root declared -- the family keeps
     * its ad-hoc posture and every explicit knob semantics is
     * unchanged).
     *
     * @throws ConfigException when the storage map is malformed or
     *                         carries an unknown knob
     */
    private static function paths(Config $config): ?StoragePaths
    {
        $root = StorageRootDeclaration::root($config);
        if ($root === null) {
            return null;
        }

        return StorageLayout::resolve($root);
    }
}
