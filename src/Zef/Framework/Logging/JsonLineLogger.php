<?php

declare(strict_types=1);

namespace Zef\\Framework\\Logging;

use InvalidArgumentException;
use Psr\\Log\\LoggerInterface;
use RuntimeException;
use Stringable;

/**
 * Dependency-free PSR-3 logger that writes one structured JSON object per line.
 *
 * The destination directory must already exist, be outside the document root,
 * and be writable by the worker. Log files are restricted to owner-only access.
 */
final readonly class JsonLineLogger implements LoggerInterface
{
    private const array LEVELS = [
        'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug',
    ];

    private string $path;

    public function __construct(string $path)
    {
        if ($path === '' || str_contains($path, "\0") || is_dir($path) || is_link($path)) {
            throw new InvalidArgumentException('The JSON log path must be a non-symlink file in an existing directory.');
        }

        $directory = realpath(dirname($path));
        $filename = basename($path);
        if (!is_string($directory) || $filename === '' || $filename === '.' || $filename === '..') {
            throw new InvalidArgumentException('The JSON log path must be a file in an existing directory.');
        }

        $resolvedPath = $directory . DIRECTORY_SEPARATOR . $filename;
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $resolvedDocumentRoot = is_string($documentRoot) && $documentRoot !== ''
            ? realpath($documentRoot)
            : false;

        if (
            is_string($resolvedDocumentRoot)
            && str_starts_with(
                $resolvedPath . DIRECTORY_SEPARATOR,
                rtrim($resolvedDocumentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
            )
        ) {
            throw new InvalidArgumentException('The JSON log destination must be outside the public document root.');
        }

        if (file_exists($resolvedPath)) {
            if (!is_file($resolvedPath) || is_link($resolvedPath)) {
                throw new InvalidArgumentException('The JSON log destination must be a regular non-symlink file.');
            }

            $permissions = fileperms($resolvedPath);
            if (!is_int($permissions) || ($permissions & 0077) !== 0) {
                throw new InvalidArgumentException('The existing JSON log file must have owner-only permissions.');
            }
        }

        $this->path = $resolvedPath;
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param mixed $level
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!is_string($level) || !in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException('Unknown PSR-3 log level.');
        }

        $line = json_encode([
            'timestamp' => gmdate('Y-m-d\\TH:i:s\\Z'),
            'level' => $level,
            'message' => LogContextSanitizer::redact((string) $message),
            'context' => LogContextSanitizer::sanitize($context),
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";

        if (is_link($this->path)) {
            throw new RuntimeException('Refusing to write through a symlink log destination.');
        }

        $stream = fopen($this->path, 'ab');
        if ($stream === false) {
            throw new RuntimeException('Unable to open the configured JSON log destination.');
        }

        $locked = false;
        $startOffset = 0;
        try {
            if (!flock($stream, LOCK_EX)) {
                throw new RuntimeException('Unable to acquire the JSON log write lock.');
            }
            $locked = true;

            $opened = fstat($stream);
            $current = lstat($this->path);
            if (
                !is_array($opened)
                || !is_array($current)
                || is_link($this->path)
                || $opened['dev'] !== $current['dev']
                || $opened['ino'] !== $current['ino']
            ) {
                throw new RuntimeException('The JSON log destination changed while it was being opened.');
            }

            if (!chmod($this->path, 0600)) {
                throw new RuntimeException('Unable to secure the JSON log destination permissions.');
            }

            $startOffset = (int) $opened['size'];
            $offset = 0;
            $length = strlen($line);
            while ($offset < $length) {
                $written = fwrite($stream, substr($line, $offset));
                if ($written === false || $written === 0) {
                    if (!ftruncate($stream, $startOffset)) {
                        throw new RuntimeException('Unable to roll back an incomplete JSON log record.');
                    }

                    throw new RuntimeException('Unable to write a complete JSON log record.');
                }

                $offset += $written;
            }
        } finally {
            if ($locked) {
                flock($stream, LOCK_UN);
            }
            fclose($stream);
        }
    }
}
