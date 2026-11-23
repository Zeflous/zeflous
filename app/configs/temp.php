<?php

declare(strict_types=1);

/*
 * The temp family's configuration file: everything under this
 * key is the `temp` map the lane's writer with its cadenced
 * sweep reads -- the transient scratch's mortal home.
 *
 * The temp family
 * ---------------
 *
 *   'path'                      (string, default '')
 *       The writer's directory: an explicitly spelled address
 *       always wins (judged by the writer's own privacy posture
 *       -- existing, writable, non-symlink, owner-only, outside
 *       the public document root), and an empty string derives
 *       from the storage layout's `temp` lane when
 *       `storage.root` declares one (the same one-way precedence
 *       every storage family keeps). An armed writer with neither
 *       an address nor a declared root is a named halt -- a
 *       directory the host never asked for is never silently
 *       defaulted.
 *
 *   'ttl'                       (int, default null)
 *       The retention: how many seconds a scratch file may live
 *       before the cadenced sweep retires it -- a file swept
 *       exactly when its own retention fully elapses, the last
 *       instant before it is not. At least one second; an
 *       environment overlay may spell it as an all-digit string
 *       (`APP_TEMP__TTL=3600`) and it folds to the integer it
 *       spells. The null default (the shipped posture) declares
 *       no armed writer: resolving the framework's `temp.store`
 *       service while it stays null is the halt that names the
 *       way out -- spell how long the lane keeps its files, or
 *       compose the writer by hand over the layout address. A
 *       host's declaration is itself the opt-in: the retention
 *       the host spells is the mortality the lane keeps, and a
 *       knob the host never spelled keeps the surface shut.
 *
 * The landed name, the write discipline and the sweep cadence
 * stay deliberately OUT of this block: the name is the writer's
 * own random draw (no host spelling ever reaches a filesystem
 * call), the one-rename staging discipline is the family's own
 * write grammar, and the cadence is the family's own cost bound
 * -- one lane walk per minute riding a write the process was
 * already paying, never a background worker -- the same const
 * the session lane's own sweep keeps. The marker that holds the
 * cadence's memory is the lane's own dot-file, never a knob:
 * a cost bound the host cannot spell wrong is a cost bound
 * the family owns outright.
 */

return [
    'path' => '',
    'ttl' => null,
];
