<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use InvalidArgumentException;
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
        [$body, $bodyReplayable] = ReplayBodyCapture::capture($request);
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
            if (is_file($destination) && !unlink($destination)) {
                throw new RuntimeException('Unable to remove an incomplete replay snapshot.', 0, $throwable);
            }

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
