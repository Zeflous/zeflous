<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The scheme guard for {@see StreamOpener}.
 *
 * The opener is the HTTP layer's single `fopen()` seam, so it is also the one
 * place where a caller-supplied URI could reach a wrapper the layer never
 * intended: a remote transport (`http://`, `ftp://`), a deserialising archive
 * (`phar://`), a command runner (`expect://`) or an inline payload (`data://`).
 * The guard is an allow-list -- the default is refusal -- with three permitted
 * shapes:
 *
 * - a plain filesystem path (no scheme), which is what the PSR-17 file factory
 *   and the upload leaf open;
 * - `php://` restricted to the in-process targets the layer actually uses
 *   (`temp`, `memory`, `input`, `stdin`, `stdout`, `stderr`); and
 * - `file://`, the explicit local-file form.
 *
 * A scheme that is none of those is refused unless it is a userland wrapper
 * registered in this process (`stream_get_wrappers()`) and is not a built-in
 * transport -- the test suite registers such wrappers to exercise the failure
 * paths, and an in-process userland wrapper is not a remote transport.
 *
 * The URI is folded to lower case exactly once, at the top of {@see assert()},
 * and every later step reads the folded string. Folding the whole URI (rather
 * than only a captured scheme) keeps the fold observable: a URI whose scheme
 * opens with a character that folds into the ASCII scheme grammar (U+212A to
 * `k`, U+017F to `s`) is recognised as schemeful and refused, where a byte-wise
 * fold would leave it non-ASCII and treat the whole string as a plain path.
 *
 * Static helper; the opener is its single consumer.
 */
final class StreamScheme
{
    /**
     * The `php://` targets the layer is allowed to open.
     */
    private const array PHP_TARGETS = ['temp', 'memory', 'input', 'stdin', 'stdout', 'stderr'];

    /**
     * The built-in wrappers that reach outside the process or deserialise
     * untrusted bytes; never permitted, even when registered.
     */
    private const array TRANSPORTS = [
        'http', 'https', 'ftp', 'ftps', 'ssh2', 'phar', 'expect', 'data',
        'zip', 'compress.zlib', 'compress.bzip2', 'glob', 'rar', 'ogg',
        'zlib', 'tcp', 'udp', 'unix', 'ssl', 'tls',
    ];

    /**
     * @param non-empty-string $uri the resource URI about to be opened
     *
     * @throws HttpException when the URI's scheme is not permitted
     */
    public static function assert(string $uri): void
    {
        $folded = mb_strtolower($uri);
        $scheme = self::scheme($folded);

        if ($scheme === '' || $scheme === 'file') {
            return;
        }

        if ($scheme === 'php') {
            self::assertPhpTarget($folded);
        } elseif (\in_array($scheme, self::TRANSPORTS, true)) {
            throw new HttpException("Refusing to open the '{$scheme}://' wrapper.");
        } elseif (!\in_array($scheme, stream_get_wrappers(), true)) {
            throw new HttpException("Refusing to open the unknown '{$scheme}://' wrapper.");
        }
    }

    /**
     * @param non-empty-string $uri the already-folded URI
     *
     * @throws HttpException when the `php://` target is not permitted
     */
    private static function assertPhpTarget(string $uri): void
    {
        $target = explode('/', (string) preg_replace('/^php:\/\//', '', $uri))[0];

        if (!\in_array($target, self::PHP_TARGETS, true)) {
            throw new HttpException("Refusing to open the 'php://{$target}' target.");
        }
    }

    /**
     * The scheme of the already-folded URI, or an empty string for a plain path.
     * The scheme grammar is ASCII, so the fold has already done the whole job.
     */
    private static function scheme(string $uri): string
    {
        if (preg_match('/^([a-z][a-z0-9+.\-]*):\/\//', $uri, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }
}
