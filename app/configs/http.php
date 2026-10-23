<?php

declare(strict_types=1);

/*
 * HTTP error-handling policy.
 *
 * Debug disclosure is fail-closed: setting debug=true is not sufficient by
 * itself. APP_ENV must explicitly be local, development, dev, test or testing.
 * Leave debug=false in production.
 *
 * log_path is optional. An empty path writes structured JSON to PHP's SAPI
 * error log. A non-empty path writes JSONL to an existing, writable directory
 * outside the public document root; the directory is never created for you.
 * Replay is opt-in and also requires debug=true plus a local APP_ENV. Its
 * storage directory must exist and remain outside the public document root.
 *
 * process_boundary is opt-in: the boot seam installs the process-level error
 * face -- the engine's own diagnostics (warnings, notices, deprecations), the
 * uncaught throwable, and the fatal only the shutdown arm can witness -- onto
 * the same error system the request boundary feeds. A process is a
 * process-wide commitment made once at the entry point, so the reference
 * profile spells the honest default and a host that wants it flips the knob.
 */

return [
    'error_handling' => [
        'debug' => false,
        'log_path' => '',
        'replay_enabled' => false,
        'replay_path' => '',
        'replay_max_snapshots' => 1000,
        'process_boundary' => false,
    ],
];
