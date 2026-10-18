# Error Handling

ZEF's global error boundary is registered as the \`http.errors\` framework service and is placed outermost in the shipped \`middleware.stack\`. Unhandled exceptions from downstream middleware and handlers are converted into a consistent response.

## Response contract

By default, failures return RFC 9457 Problem Details:

\`\`\`http
Content-Type: application/problem+json; charset=utf-8
X-Request-ID: <request correlation id>
Cache-Control: no-store
\`\`\`

The JSON document includes \`type\`, \`title\`, \`status\`, \`detail\`, \`instance\`, and \`trace_id\`. The \`instance\` contains only the request path, not the query string. Typed framework exceptions also expose a stable \`code\`. HTTP 405 responses include \`Allow\`; HTTP 429 responses include \`Retry-After\`.

When a request explicitly prefers \`text/html\` over JSON, ZEF renders an escaped branded HTML page. Production responses do not include stack traces or developer details. Debug details are redacted and bounded before rendering.

## Configuration

\`app/configs/http.php\` configures the error boundary:

- \`error_handling.debug\`: defaults to \`false\`. Debug output is enabled only when this is \`true\` **and** \`APP_ENV\` is one of \`local\`, \`development\`, \`dev\`, \`test\`, or \`testing\`. If \`APP_ENV\` is missing or production-like, debug output remains disabled.
- \`error_handling.log_path\`: defaults to an empty string, which writes structured JSON records to PHP's SAPI error log. Set a non-empty path to use the JSONL logger. The parent directory must already exist, be writable by the worker, and be outside the public document root.
- \`observability.metrics.enabled\`: when enabled, each mapped failure increments \`zef.http.errors.total\` with bounded method, status, and exception-type dimensions. The metrics exposition endpoint remains disabled unless the metrics family is explicitly enabled.

Example for local development:

\`\`\`php
return [
    'error_handling' => [
        'debug' => true,
        'log_path' => '/var/log/zef/errors.jsonl',
    ],
];
\`\`\`

Set \`APP_ENV=local\` in the development process. Do not enable debug output in production.

## JSONL logger and CLI

\`Zef\\Framework\\Logging\\JsonLineLogger\` implements the vendored PSR-3 contract without runtime dependencies. It writes one JSON record per line, locks each write, bounds context, converts exception objects into safe summaries, and redacts common credential fields and values.

The local inspector reads JSONL logs:

\`\`\`bash
bin/zef errors:report /var/log/zef/errors.jsonl
bin/zef errors:clear /var/log/zef/errors.jsonl --confirm
\`\`\`

The clear command truncates the specified local log file; it does not clear a distributed queue. It requires \`--confirm\` and refuses symlink destinations. Keep log retention, rotation, permissions, and backup policy under the deployment's operational controls.

## Debug toolbar and local request replay

The ZEF Profiler toolbar is enabled only when \`http.error_handling.debug=true\` and \`APP_ENV\` is local/development/test. It is injected only into small, seekable, uncompressed HTML 2xx responses; other responses are passed through unchanged. HTML error pages in debug mode include duration, query count, exception count and trace ID. Database adapters can increment the request-scoped counter through the \`ErrorRequestContext\` request attribute; the framework does not assume a database driver.

Example adapter hook:

\`\`\`php
$context = $request->getAttribute(\Zef\Framework\Http\ErrorRequestContext::ATTRIBUTE);
if ($context instanceof \Zef\Framework\Http\ErrorRequestContext) {
    $context->recordQuery();
}
\`\`\`



Replay is disabled by default. To enable it in local development, configure an existing writable directory outside the document root:

\`\`\`php
return [
    'error_handling' => [
        'debug' => true,
        'log_path' => '/var/log/zef/errors.jsonl',
        'replay_enabled' => true,
        'replay_path' => '/var/lib/zef/error-replays',
    ],
];
\`\`\`

With \`APP_ENV=local\`, failed requests are captured into exclusive files with mode \`0600\`. Authorization/cookie headers are omitted; query parameters and JSON or URL-encoded body fields with sensitive names are redacted. Other body formats are omitted rather than persisted raw. Replayed bodies may therefore differ from the original and should be used for debugging, not forensic byte-for-byte reproduction. The CLI refuses non-loopback targets and disables redirects:

\`\`\`bash
bin/zef errors:replay <snapshot-id> /var/lib/zef/error-replays http://127.0.0.1:8080
\`\`\`

The replay target is deliberately limited to \`localhost\`, \`127.0.0.1\`, or \`::1\`; the command does not forward credentials or cookies. Protect the replay directory as sensitive development data and clear it under your local retention policy.

## Operational boundaries

- The request correlation ID is emitted as \`trace_id\` when no explicit trace ID is carried by the exception. This keeps the response and log record correlated; full OpenTelemetry trace-ID handoff through the middleware boundary remains separate work.
- The optional file logger requires an existing directory and never creates one implicitly.
- Query strings, request bodies, and authorization/cookie headers are not included in the default error log context.
- Error replay and a full profiler toolbar are not yet implemented.
