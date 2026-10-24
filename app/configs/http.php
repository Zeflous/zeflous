<?php

declare(strict_types=1);

/*
 * HTTP families: error-handling policy, the output-compression
 * engine, the multi-format body parser, and the session/cookie
 * abstraction.
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

/*
 * Body-parser family
 * -------------------
 *
 * The multi-format request-body parser (`http.body_parser`, the
 * service the framework registers ahead of the composition; the
 * middleware the stack composes -- a host that wants parsed bodies
 * adds `http.body_parser` to its `middleware.stack`, which is a stack
 * write, not a service definition):
 *
 *   'enabled'                   (bool, default true)
 *       The family's arm. A disabled family is a pass-through: the
 *       middleware stays in the stack (its membership is the host's
 *       declaration) but reads no media type and parses nothing.
 *
 *   'json_depth'                (int, default 32, 1..512)
 *       The recursion ceiling the JSON strategy asks the engine for.
 *       The default is the error system's own documented ceiling, so
 *       the two JSON decoders in the process refuse at the same
 *       depth; the corridor's ceiling is the engine's own maximum.
 *
 * The family owns `application/json` (and its `+json` vendor
 * members), `application/xml`, `text/xml` (and `application/+xml`
 * vendor members), `application/x-www-form-urlencoded` on the
 * non-SAPI path, and `text/plain`. Multipart stays with the engine's
 * own SAPI decode; a malformed owned body refuses with a 400 before
 * the controller runs.
 */

/*
 * Session family
 * ---------------
 *
 * The session and cookie abstraction (`http.session`, the service the
 * framework registers ahead of the composition; the middleware the
 * stack composes -- a host that wants sessions adds `http.session`
 * to its `middleware.stack`, which is a stack write, not a service
 * definition):
 *
 *   'enabled'                   (bool, default true)
 *       The family's arm. A disabled family is a pass-through: the
 *       middleware stays in the stack (its membership is the host's
 *       declaration) but reads no cookie and loads no session.
 *
 *   'cookie_name'               (string, default 'zef_session')
 *       The session cookie's name (an RFC 9110 token).
 *
 *   'idle_timeout'              (int, default 1800, 1..2592000)
 *       The storage-side sliding expiry, in seconds: every write
 *       refreshes it, so activity extends a session and only
 *       write-idleness ends one. The default is thirty minutes.
 *
 *   'id_bytes'                  (int, default 32, 16..64)
 *       The session identifier's entropy, in bytes -- folded through
 *       base64url into the cookie value (32 bytes spells as 43
 *       characters).
 *
 *   'cookie_secure'             (bool, default true)
 *   'cookie_http_only'          (bool, default true)
 *   'same_site'                 (string, default 'Lax')
 *       The cookie's security flags, the roadmap's promised secure
 *       trio: HttpOnly on, Secure armed (emitted over https, or
 *       whenever same_site is None -- the one exception the browser
 *       contract forces), and SameSite=Lax. same_site 'None'
 *       requires cookie_secure, refused at validation.
 *
 * The storage behind the family is the session adapter port; the
 * shipped driver is the in-memory one (single-worker, test, and
 * local scope). A deployment preferring another storage composes the
 * middleware over its own driver implementing the port -- a
 * constructor write, never an application change.
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
    'body_parser' => [
        'enabled' => true,
        'json_depth' => 32,
    ],
    'session' => [
        'enabled' => true,
        'cookie_name' => 'zef_session',
        'idle_timeout' => 1800,
        'id_bytes' => 32,
        'cookie_secure' => true,
        'cookie_http_only' => true,
        'same_site' => 'Lax',
    ],
];
