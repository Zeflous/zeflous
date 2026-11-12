<?php

declare(strict_types=1);

/*
 * The session read-modify-write lane's marker child: the pure-protocol
 * sibling a blocking test spawns -- the engine's own flock taken on the
 * sidecar exactly as the driver's lock fold takes it (a blocking LOCK_EX
 * over the same companion), the marker file written only once the lane
 * is granted. The parent that holds the lane through its own adapter
 * asserts the marker is absent until ITS release, and present after --
 * the cross-process half of the lane's reality, pinned against the same
 * lock machinery the sibling requests themselves will ride.
 *
 * Usage: php SessionLaneMarkerChild.php <sidecar-path> <marker-path>
 * Exits 0 once the lane was granted, the marker written, and the lane
 * released -- a process that never holds the lane never marks.
 *
 * @internal
 */

$sidecar = $argv[1] ?? '';
$marker = $argv[2] ?? '';

if ($sidecar === '' || $marker === '') {
    fwrite(STDERR, "usage: SessionLaneMarkerChild.php <sidecar-path> <marker-path>\n");

    exit(2);
}

$handle = fopen($sidecar, 'cb');

if ($handle === false) {
    fwrite(STDERR, "the sidecar could not be opened\n");

    exit(3);
}

flock($handle, LOCK_EX);
file_put_contents($marker, 'acquired');
flock($handle, LOCK_UN);
fclose($handle);

exit(0);
