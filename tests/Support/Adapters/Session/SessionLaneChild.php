<?php

declare(strict_types=1);

/*
 * The session read-modify-write lane's child process: the real engine
 * child a concurrent-request test spawns -- the issue #781 repro's own
 * sibling, run exactly as the OTLP mock server and the unpositionable
 * stream's own children run (the array-command form, the engine's own
 * binary, no shell wrapper).
 *
 * The child performs ONE full request's read-modify-write over the
 * REAL driver -- the same verbs the HTTP session middleware composes:
 * the load that takes the lane, the bag read under it, the caller's own
 * write merged into whatever the sibling request already wrote, and
 * the save that releases the lane. Two children spawned together are
 * the issue's own overlapping requests on one session (two tabs, a
 * double-submit, an AJAX poller): whichever child's load runs second
 * BLOCKS on the lane the first child's save releases, so its bag is
 * read over the first child's write -- both requests keep their
 * writes, where the last-write-wins interleave silently lost one.
 *
 * Usage: php SessionLaneChild.php <store-directory> <session-id> <own-key>
 * Exits 0 on the completed round trip; anything the lane or the store
 * refuses answers the process's own non-zero exit.
 *
 * @internal
 */

require dirname(__DIR__, 4) . '/vendor/autoload.php';

use Zef\Framework\Adapters\Session\FileSessionAdapter;

$storeDirectory = $argv[1] ?? '';
$sessionId = $argv[2] ?? '';
$ownKey = $argv[3] ?? '';

if ($storeDirectory === '' || $sessionId === '' || $ownKey === '') {
    fwrite(STDERR, "usage: SessionLaneChild.php <store-directory> <session-id> <own-key>\n");

    exit(2);
}

$fileSessionAdapter = new FileSessionAdapter($storeDirectory, 1800);

$bag = $fileSessionAdapter->load($sessionId) ?? [];
$bag[$ownKey] = true;
$fileSessionAdapter->save($sessionId, $bag);

exit(0);
