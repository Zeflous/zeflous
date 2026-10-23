<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Http\ErrorReplayStore;

/**
 * The error-replay wiring fold of the error mapping factory: the replay
 * section of the frozen `http.error_handling` configuration, read, validated,
 * and answered as one wired store (or the honest null of a lane that is off).
 *
 * The fold exists because the factory's own fromConfig seam was already
 * carrying the configuration family's full validation weight -- every key
 * its own fail-closed refusal -- and the retention bound the replay lane
 * gained (#577) was the decision that carried that weight over the
 * architecture gate's complexity ceiling. The replay keys move here as one
 * cohesive grammar: the enabled flag, the storage path, and the snapshot
 * bound each refuse their own malformed shape, and the combination check
 * keeps the lane's own security posture -- replay demands debug=true, a
 * local APP_ENV, and a configured path, because the snapshots are sanitized
 * but still sensitive development data.
 */
final class ErrorReplayWiring
{
    /**
     * Answers the replay store the configuration asks for, or null when the
     * lane is disabled.
     *
     * @throws ConfigException when any replay key is malformed or the
     *                         enabled combination is refused
     */
    public static function store(Config $config, bool $debug, bool $debugAllowed): ?ErrorReplayStore
    {
        $replayEnabled = $config->get('http.error_handling.replay_enabled', false);
        if (!\is_bool($replayEnabled)) {
            throw new ConfigException('http.error_handling.replay_enabled must be a boolean.');
        }

        $replayPath = $config->get('http.error_handling.replay_path', '');
        if (!\is_string($replayPath)) {
            throw new ConfigException('http.error_handling.replay_path must be a string.');
        }

        $replayMaxSnapshots = $config->get(
            'http.error_handling.replay_max_snapshots',
            ErrorReplayStore::DEFAULT_MAX_SNAPSHOTS,
        );
        if (!\is_int($replayMaxSnapshots) || $replayMaxSnapshots < 1) {
            throw new ConfigException('http.error_handling.replay_max_snapshots must be an integer of 1 or above.');
        }

        if ($replayEnabled && (!$debug || !$debugAllowed || $replayPath === '')) {
            throw new ConfigException(
                'Request replay requires debug=true, a local APP_ENV, and a configured replay_path.',
            );
        }

        return $replayEnabled ? new ErrorReplayStore($replayPath, $replayMaxSnapshots) : null;
    }
}
