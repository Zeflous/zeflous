<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use JsonException;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Stores sanitized failed-request snapshots in an explicitly configured local
 * directory. Only JSON and URL-encoded bodies are replayable; other bodies are
 * omitted rather than persisted without a format-aware redaction pass.
 */
final readonly class ErrorReplayStore
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = ReplayStorageDirectory::resolve($directory);
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

        ReplaySnapshotWriter::write($this->directory, $id, $json);

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
