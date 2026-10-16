<?php

declare(strict_types=1);

namespace Zef\Test\Support\Telemetry;

/*
 * The OTLP mock server's router script: the endpoint the exporter's
 * transport actually POSTs to, over the engine's own built-in
 * development server (`php -S`), started and polled by {@see
 * OtlpMockServer}.
 *
 * The script carries the namespace its directory owns -- the same
 * discipline every other support double in the tree carries -- so
 * the native constants it reads are referenced the way every
 * namespaced file in the repository references them: fully
 * qualified, with the leading backslash the fixer's strict
 * native-constant rule mandates (and the sniffs' namespace-less
 * complaint never applies).
 *
 * Behaviour is declared through the environment the helper passes:
 *
 *   OTLP_STATUS    the status every answered request carries (default
 *                  200 -- the delivery success path)
 *   OTLP_DUMP      the file every request's body is appended to, one
 *                  line per request (the payload the exporter sent)
 *   OTLP_COUNT     the file the request counter reads and writes --
 *                  the retry lane's observable, one increment per
 *                  received request
 *   OTLP_FAIL_FIRST the number of leading requests answered with 500
 *                  (the transient rejection the lane retries); requests
 *                  past the count answer OTLP_STATUS
 *   OTLP_DELAY_MS  the milliseconds every answer is held back for --
 *                  the slow collector the exchange's own timeout
 *                  bounds
 */

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = (string) parse_url(\is_string($requestUri) ? $requestUri : '/', \PHP_URL_PATH);
$countFile = getenv('OTLP_COUNT');
$countFile = $countFile === false ? '' : $countFile;
$counter = 0;

if ($uri !== '/v1/traces') {
    http_response_code(404);

    return;
}

if ($countFile !== '' && is_file($countFile)) {
    $counter = (int) file_get_contents($countFile);
}

if ($countFile !== '') {
    file_put_contents($countFile, (string) ($counter + 1));
}

$dumpFile = getenv('OTLP_DUMP');
$dumpFile = $dumpFile === false ? '' : $dumpFile;

if ($dumpFile !== '') {
    file_put_contents($dumpFile, file_get_contents('php://input') . "\n", \FILE_APPEND);
}

$delayMs = getenv('OTLP_DELAY_MS');
$delayMs = (int) ($delayMs === false || $delayMs === '' ? '0' : $delayMs);

if ($delayMs > 0) {
    usleep($delayMs * 1000);
}

$failFirst = getenv('OTLP_FAIL_FIRST');
$failFirst = (int) ($failFirst === false || $failFirst === '' ? '0' : $failFirst);

if ($counter < $failFirst) {
    http_response_code(500);

    return;
}

$status = getenv('OTLP_STATUS');
http_response_code((int) ($status === false || $status === '' ? '200' : $status));
