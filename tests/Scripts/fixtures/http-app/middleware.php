<?php

declare(strict_types=1);

/*
 * The HTTP inspection fixture application's stack: the framework's
 * own error boundary outermost (so a routing refusal answers as the
 * RFC 9457 problem the wire would carry), then the framework's own
 * `security.headers` registration -- installed by the Default layer,
 * not by any fixture file -- opted into the pipeline by name, so the
 * audited response carries the hardening the application actually
 * stamped.
 */

return [
    'stack' => [
        ['service' => 'http.errors', 'priority' => 1000],
        ['service' => 'security.headers', 'priority' => 500],
    ],
];
