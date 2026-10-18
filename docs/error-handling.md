# Error Handling

ZEF's global error boundary is registered as the `http.errors` framework service and is placed outermost in the shipped `middleware.stack`. Unhandled exceptions from downstream middleware and handlers are converted into a consistent response.

## Response contract

By default, failures return RFC 9457 Problem Details:

```http
Content-Type: application/problem+json; charset=utf-8
X-Request-ID: <request correlation id>
Cache-Control: no-store
```

The JSON document includes `type`, `title`, `status`, `detail`, `instance`, and `trace_id`. The `instance` contains only the request path, not the query string. Typed framework exceptions expose a stable `code` matching `[A-Z][A-Z0-9_]{0,63}`; supplied trace IDs are bounded to 128 safe ASCII characters. Invalid UTF-8 in diagnostic text is substituted during JSON encoding so the original HTTP status and response media type can be retained. HTTP 405 responses include `Allow`; HTTP 429 responses include `Retry-After`.

When a request explicitly prefers `text/html` over JSON, ZEF renders an escaped branded HTML page. Production responses do not include stack traces or developer details. Debug details are redacted and bounded before rendering. PDO failures are normalized to a safe `DATABASE_ERROR` response with SQLSTATE-only diagnostics; Guzzle failures are recognized by interface name without requiring Guzzle as a runtime dependency.

## Configuration

`app/configs/http.php` configures the error boundary:

- `error_handling.debug`: defaults to `false`. Debug output is enabled only when this is `true` **and** `APP_ENV` is one of `local`, `development`, `dev`, `test`, or `testing`. If `APP_ENV` is missing or production-like, debug output remains disabled.
- `error_handling.log_path`: defaults to an empty string, which writes structured JSON records to PHP's SAPI error log. Set a non-empty path to use the JSONL logger. The parent directory must already exist, be writable by the worker, and be outside the public document root.
- `observability.metrics.enabled`: when enabled, each mapped failure increments `zef.http.errors.total` with bounded method, status, and exception-type dimensions. The metrics exposition endpoint remains disabled unless the metrics family is explicitly enabled.

Example for local development:

```php
return [
    'error_handling' => [
        'debug' => true,
        'log_path' => '/var/log/zef/errors.jsonl',
    ],
];
```

Set `APP_ENV=local` in the development process. Do not enable debug output in production.

## JSONL logger and CLI

`Zef\\Framework\\Logging\\JsonLineLogger` implements the vendored PSR-3 contract without runtime dependencies. It writes one JSON record per line, locks each write, restricts file permissions to owner-only, rejects document-root destinations and symlinks, bounds context, and redacts common credential fields and values. The default error context excludes raw exception objects, user IDs, IP addresses, user-agent strings, query strings and request bodies. Credential redaction is heuristic, so do not place secrets in exception messages.

The local inspector reads JSONL logs:

```bash
bin/zef errors:report /var/log/zef/errors.jsonl
bin/zef errors:report /var/log/zef/errors.jsonl --request-id=req-123
bin/zef errors:clear /var/log/zef/errors.jsonl --confirm
```

The clear command truncates the specified local log file; it does not clear a distributed queue. It requires `--confirm` and refuses symlink destinations. Keep log retention, rotation, permissions, and backup policy under the deployment's operational controls.

## Debug toolbar and local request replay

The ZEF Profiler toolbar is enabled only when `http.error_handling.debug=true` and `APP_ENV` is local/development/test. It is injected only into small, seekable, uncompressed HTML 2xx responses; other responses are passed through unchanged. HTML error pages in debug mode include duration, query count, exception count and trace ID. Database adapters can increment the request-scoped counter with `ErrorRequestContext::recordQueryFor($request)` or through the `ErrorRequestContext` request attribute; the framework does not assume a database driver. The toolbar provides a collapsible request/log reference, and debug error pages open a CSS-only side panel with exception details and the CLI command to locate matching records by request ID.

Example adapter hook:

```php
\Zef\Framework\Http\ErrorRequestContext::recordQueryFor($request);
```



Replay is disabled by default. To enable it in local development, configure an existing writable directory with owner-only permissions (`0700`) outside the document root:

```php
return [
    'error_handling' => [
        'debug' => true,
        'log_path' => '/var/log/zef/errors.jsonl',
        'replay_enabled' => true,
        'replay_path' => '/var/lib/zef/error-replays',
    ],
];
```

With `APP_ENV=local`, failed requests are captured into exclusive files with mode `0600`. Authorization/cookie headers are omitted; query parameters and JSON or URL-encoded body fields with sensitive names are redacted. Only JSON and URL-encoded bodies are eligible for replay after structured field redaction. Free-form text, XML, oversized, non-seekable, multipart and unsupported binary bodies are omitted because generic text redaction cannot guarantee that every secret is removed. Replayed bodies may therefore differ from the original and should be used for debugging, not forensic byte-for-byte reproduction. The CLI refuses non-loopback targets and disables redirects:

```bash
bin/zef errors:replay <snapshot-id> /var/lib/zef/error-replays http://127.0.0.1:8080
```

The replay target is deliberately limited to `localhost`, `127.0.0.1`, or `::1`; the command does not forward credentials or cookies. Protect the replay directory as sensitive development data and clear it under your local retention policy.

## Operational boundaries

- The request-scoped context adopts a valid W3C trace ID from `CorrelationMiddleware`; the same trace ID is used in Problem Details and structured logs. When no span is active, the request ID is the fallback.
- The optional file logger requires an existing directory, rejects unresolved or public document roots, restricts log files to owner-only permissions (`0600`), and never creates directories implicitly.
- Query strings, request bodies, and authorization/cookie headers are not included in the default error log context.
- Query counts remain zero unless the database adapter calls `ErrorRequestContext::recordQueryFor($request)` for each executed query. Unsupported or unsafe body formats are intentionally omitted from replay snapshots.
