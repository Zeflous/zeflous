<?php

declare(strict_types=1);

/*
 * HTTP families: error-handling policy and the output-compression engine.
 *
 * Error-handling policy
 * ---------------------
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
 *
 * Compression family
 * ------------------
 *
 * The output-compression engine (`http.compression`, the service the
 * framework registers ahead of the composition; the middleware the
 * stack composes -- a host that wants compressed answers adds
 * `http.compression` to its `middleware.stack`, which is a stack
 * write, not a service definition):
 *
 *   'enabled'                   (bool, default true)
 *       The family's arm. A disabled family is a pass-through: the
 *       middleware stays in the stack (its membership is the host's
 *       declaration) but reads no encoding line and compresses
 *       nothing.
 *
 *   'minimum_bytes'             (int, default 1024, 1..1048576)
 *       The threshold: only bodies at or above it compress. The
 *       default is the roadmap's own example -- compressing a
 *       300-byte JSON reply spends more CPU than the bytes save. A
 *       body whose size the stream cannot report compresses without
 *       the threshold's protection (buffering the whole body to
 *       learn its size is the memory posture the family exists to
 *       avoid).
 *
 *   'level'                     (int, default 6, 1..9)
 *       The zlib compression level the engine asks for. Six is
 *       zlib's own middle, where the ratio curve has already
 *       flattened and the cost curve has not steepened.
 *
 * The engine negotiates the client's `Accept-Encoding` against what
 * this PHP build can produce (brotli only behind its extension;
 * gzip and deflate over the bundled zlib engine) and refuses to
 * double-encode a response that already carries a Content-Encoding.
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
    'compression' => [
        'enabled' => true,
        'minimum_bytes' => 1024,
        'level' => 6,
    ],
];
