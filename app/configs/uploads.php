<?php

declare(strict_types=1);

/*
 * The uploads family's configuration file: everything under this
 * key is the `uploads` map the lane's first writer reads -- the
 * inbound files' validated landing surface.
 *
 * The uploads family
 * ------------------
 *
 *   'path'                      (string, default '')
 *       The writer's directory: an explicitly spelled address
 *       always wins (judged by the writer's own privacy posture
 *       -- existing, writable, non-symlink, owner-only, outside
 *       the public document root), and an empty string derives
 *       from the storage layout's `uploads` lane when
 *       `storage.root` declares one (the same one-way precedence
 *       every storage family keeps). An armed writer with neither
 *       an address nor a declared root is a named halt -- a
 *       directory the host never asked for is never silently
 *       defaulted.
 *
 *   'max_bytes'                 (int, default 2097152)
 *       The writer's size ceiling: every upload whose measured
 *       size crosses it is refused with the evidence carried
 *       (413, the error boundary's RFC 9457 rendering). At least
 *       one byte; an environment overlay may spell it as an
 *       all-digit string (`APP_UPLOADS__MAX_BYTES=5242880`) and
 *       it folds to the integer it spells. A size the stream
 *       cannot answer is refused as the honest validation error
 *       it is -- never a 413 that would name a number the engine
 *       does not hold.
 *
 *   'types'                     (list of strings, default [])
 *       The media-type allowlist the exact-match comparison
 *       keeps: each entry the lowercase canonical spelling
 *       (`image/png`, `application/pdf` -- uppercase entries,
 *       parameters and typo'd shapes are named halts, never
 *       silently accepted). The empty list (the shipped default)
 *       declares no armed writer: resolving the framework's
 *       `uploads.store` service while it stays empty is the halt
 *       that names the way out -- spell the types the lane
 *       accepts, or compose the writer by hand over the layout
 *       address. A host's declaration is itself the opt-in: the
 *       writer never accepts a type the host did not spell.
 *
 * The landed name, the sweep cadence and the cross-process
 * transports stay deliberately OUT of this block: the name is the
 * writer's own random draw (no client spelling ever reaches a
 * filesystem call), the `temp` lane's writer and its cadenced
 * sweep live in their own family's block (`temp.php`, the
 * retention the host spells), and a quarantined-refusals lane
 * would be a knob without a reader today -- the census-moment
 * doctrine keeps each one out until the fold that reads it
 * lands.
 */

return [
    'path' => '',
    'max_bytes' => 2097152,
    'types' => [],
];
