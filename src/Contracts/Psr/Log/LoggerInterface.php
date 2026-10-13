<?php

declare(strict_types=1);

namespace Psr\Log;

/**
 * Describes a logger instance, as defined by PSR-3.
 *
 * Vendored by the ZEF zero-library shim; byte-compatible with PSR-3 v3
 * upstream (the RFC-level method set, the `string|\Stringable` message
 * unions and the untyped `$level` parameter of `log()`), so third-party
 * loggers implement it without signature conflicts. The eight
 * level-named methods are the common case; `log()` exists so a caller
 * that holds the level as a value does not have to `match` it back into
 * a method name.
 */
interface LoggerInterface
{
    /**
     * System is unusable.
     *
     * @param array<string, mixed> $context
     */
    public function emergency(string|\Stringable $message, array $context = []): void;

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param array<string, mixed> $context
     */
    public function alert(string|\Stringable $message, array $context = []): void;

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param array<string, mixed> $context
     */
    public function critical(string|\Stringable $message, array $context = []): void;

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param array<string, mixed> $context
     */
    public function error(string|\Stringable $message, array $context = []): void;

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param array<string, mixed> $context
     */
    public function warning(string|\Stringable $message, array $context = []): void;

    /**
     * Normal but significant events.
     *
     * @param array<string, mixed> $context
     */
    public function notice(string|\Stringable $message, array $context = []): void;

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param array<string, mixed> $context
     */
    public function info(string|\Stringable $message, array $context = []): void;

    /**
     * Detailed debug information.
     *
     * @param array<string, mixed> $context
     */
    public function debug(string|\Stringable $message, array $context = []): void;

    /**
     * Logs with an arbitrary level.
     *
     * @param string               $level   the level, one of the eight method names
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void;
}
