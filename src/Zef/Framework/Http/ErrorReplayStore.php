<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

/**
 * Stores sanitized failed-request snapshots in an explicitly configured local
 * directory. Only JSON and URL-encoded bodies are replayable; other bodies are
 * omitted rather than persisted without a format-aware redaction pass.
 */
final readonly class ErrorReplayStore
{
    private const int MAX_BODY_BYTES = 65_536;

    private string $directory;

    public function __construct(string $directory)
    {
        if ($directory === '' || is_link($directory) || !is_dir($directory) || !is_writable($directory)) {
            throw new InvalidArgumentException('Replay storage must be an existing writable non-symlink directory.');
        }

        $resolved = realpath($directory);
        if (!is_string($resolved)) {
            throw new InvalidArgumentException('Replay storage directory cannot be resolved.');
        }

        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $resolvedDocumentRoot = is_string($documentRoot) && $documentRoot !== ''
            ? realpath($documentRoot)
            : false;

        if (
            is_string($resolvedDocumentRoot)
            && ($resolved === $resolvedDocumentRoot
                || str_starts_with($resolved, rtrim($resolvedDocumentRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))
        ) {
            throw new InvalidArgumentException('Replay storage must be outside the public document root.');
        }

        $this->directory = $resolved;
    }

    /**
     * @throws JsonException When a snapshot cannot be encoded.
     */
    public function save(
        ServerRequestInterface $request,
        Throwable $exception,
        int $status,
        string $requestId,
    ): string {
        [$body, $bodyReplayable] = $this->body($request);
        $path = $request->getUri()->getPath();
        if ($path === '' || preg_match('/[\x00-\x1F\x7F]/', $path) === 1 || !str_starts_with($path, '/')) {
            $path = '/';
        }

        $snapshot = [
            'id' => '',
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'request_id' => $requestId,
            'status' => $status,
            'exception_class' => $exception::class,
            'method' => strtoupper($request->getMethod()),
            'path' => $path,
            'query' => $this->query($request->getUri()->getQuery()),
            'headers' => $this->headers($request->getHeaders()),
            'body' => $body,
            'body_replayable' => $bodyReplayable,
        ];

        $id = bin2hex(random_bytes(16));
        $snapshot['id'] = $id;
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);

        $destination = $this->directory . DIRECTORY_SEPARATOR . $id . '.json';
        $stream = fopen($destination, 'xb');
        if ($stream === false) {
            throw new RuntimeException('Unable to create an exclusive replay snapshot.');
        }

        try {
            if (!chmod($destination, 0600) || !flock($stream, LOCK_EX)) {
                throw new RuntimeException('Unable to secure the replay snapshot file.');
            }

            $line = $json . "\n";
            $written = fwrite($stream, $line);
            if ($written !== strlen($line)) {
                throw new RuntimeException('Unable to write a complete replay snapshot.');
            }

            flock($stream, LOCK_UN);
        } catch (Throwable $throwable) {
            fclose($stream);
            @unlink($destination);

            throw $throwable;
        }

        fclose($stream);

        return $id;
    }

    /**
     * @param array<string, list<string>> $headers
     * @return array<string, list<string>>
     */
    private function headers(array $headers): array
    {
        $safe = [];
        foreach ($headers as $name => $values) {
            if (preg_match('/authorization|cookie|secret|token|api[_-]?key|csrf/i', $name) === 1) {
                continue;
            }

            if (in_array(strtolower($name), ['host', 'content-length', 'connection', 'transfer-encoding'], true)) {
                continue;
            }

            $safe[$name] = array_map(
                static fn (string $value): string => substr(RedactedMessage::of($value), 0, 512),
                array_slice($values, 0, 10),
            );
        }

        return $safe;
    }

    /** @return array{string, bool} sanitized body and whether it can be replayed */
    private function body(ServerRequestInterface $request): array
    {
        $stream = $request->getBody();
        $size = $stream->getSize();
        if ($size === 0) {
            return ['', true];
        }

        if (!$stream->isSeekable() || $size === null || $size > self::MAX_BODY_BYTES) {
            return ['', false];
        }

        try {
            $position = $stream->tell();
            $stream->rewind();
            $raw = $stream->getContents();
        } catch (Throwable) {
            if (isset($position)) {
                try {
                    $stream->seek($position);
                } catch (Throwable) {
                    // A failed restore is contained; capture is best-effort.
                }
            }

            return ['', false];
        }

        try {
            $stream->seek($position);
        } catch (Throwable) {
            return ['', false];
        }

        if ($raw === '') {
            return ['', true];
        }

        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        try {
            if (str_contains($contentType, 'application/json') || str_contains($contentType, '+json')) {
                $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                $safe = ReplayPayloadSanitizer::sanitize($decoded);

                return [json_encode($safe, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE), true];
            }

            if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                parse_str($raw, $decoded);
                $safe = ReplayPayloadSanitizer::sanitize($decoded);

                return [http_build_query(is_array($safe) ? $safe : []), true];
            }

            if (
                str_starts_with($contentType, 'text/')
                || str_contains($contentType, 'application/xml')
                || str_contains($contentType, 'text/xml')
                || str_contains($contentType, '+xml')
            ) {
                return [$this->sanitizeTextBody($raw, $contentType), true];
            }
        } catch (Throwable) {
            return ['', false];
        }

        return ['', false];
    }

    private function sanitizeTextBody(string $body, string $contentType): string
    {
        if (str_contains($contentType, 'xml')) {
            $redacted = preg_replace(
                '/(<([A-Z0-9_.:-]*(?:password|passwd|secret|token|authorization|cookie|csrf|api[_-]?key)[A-Z0-9_.:-]*)\b[^>]*>).*?(<\/\2\s*>)/is',
                '$1[REDACTED]$3',
                $body,
            );
            $body = is_string($redacted) ? $redacted : '[REDACTED]';
        }

        $safe = RedactedMessage::of($body);
        $safe = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $safe);

        return is_string($safe) ? substr($safe, 0, self::MAX_BODY_BYTES) : '[REDACTED]';
    }

    private function query(string $query): string
    {
        if ($query === '' || strlen($query) > 8192) {
            return '';
        }

        parse_str($query, $decoded);
        $safe = ReplayPayloadSanitizer::sanitize($decoded);

        return http_build_query(is_array($safe) ? $safe : []);
    }
}
