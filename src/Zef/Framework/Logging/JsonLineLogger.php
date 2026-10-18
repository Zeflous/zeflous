<?php

declare(strict_types=1);

namespace Zef\Framework\Logging;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Stringable;

/**
 * Dependency-free PSR-3 logger that writes one structured JSON object per line.
 *
 * The destination directory must already exist. The logger never creates
 * directories or follows a symlink destination, keeping filesystem ownership
 * and permissions an explicit deployment decision.
 */
final readonly class JsonLineLogger implements LoggerInterface
{
    private const array LEVELS = [
        'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug',
    ];

    public function __construct(private string $path)
    {
        if ($path === '' || is_dir($path) || is_link($path) || !is_dir(dirname($path))) {
            throw new InvalidArgumentException('The JSON log path must be a non-symlink file in an existing directory.');
        }
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
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
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

        try {
            if (!flock($stream, LOCK_EX)) {
                throw new RuntimeException('Unable to acquire the JSON log write lock.');
            }

            $offset = 0;
            $length = strlen($line);
            while ($offset < $length) {
                $written = fwrite($stream, substr($line, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Unable to write a complete JSON log record.');
                }

                $offset += $written;
            }

            flock($stream, LOCK_UN);
        } finally {
            fclose($stream);
        }
    }
}
