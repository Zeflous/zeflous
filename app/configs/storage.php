<?php

declare(strict_types=1);

/*
 * Storage layout: the `files/**` route map's one declared root.
 *
 * The storage family
 * ------------------
 *
 *   'root'                      (string, default '')
 *       The deployment's declared storage root: the one directory
 *       every lane (`logs/`, `cache/`, `temp/`, `uploads/`,
 *       `replay/`) joins onto. It is the only per-environment
 *       variable in the whole layout doctrine -- sandbox,
 *       development and production differ in this one address and
 *       in nothing else.
 *
 *       An empty root (the shipped default) declares no layout: the
 *       error family keeps its explicit-knob posture unchanged (an
 *       empty `http.error_handling.log_path` is the no-sink SAPI
 *       fallback, an empty `replay_path` keeps refusing an enabled
 *       replay lane). A declared root provisions the family's
 *       defaults: the structured log address derives to the `logs`
 *       lane and the replay directory to the `replay` lane, each
 *       only when its own knob is left empty -- an explicitly
 *       spelled address always wins.
 *
 *       The tree under a declared root must be provisioned before
 *       boot: all five lanes, existing, writable, non-symlink,
 *       outside the public document root. A misprovisioned tree is
 *       refused at construction with the failing seam named
 *       (`bin/zef storage:map` answers the same verdict from the
 *       shell), and the framework never creates the tree for you.
 */

return [
    'root' => '',
];
