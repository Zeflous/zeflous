<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Stores sanitized failed-request snapshots in an explicitly configured local
 * directory. Only JSON and URL-encoded bodies are replayable; other bodies are
 * omitted rather than persisted without a format-aware redaction pass.
 *
 * The directory is bounded: every save prunes the storage down to the
 * configured snapshot bound ({@see ReplayRetention}), oldest first, so an
 * error-spamming source in local development fills the bound and stops --
 * the accumulation the round-2 audit recorded (finding C-1) ends at an
 * operator-sized ceiling instead of at the filesystem's.
 */
final readonly class ErrorReplayStore
{
    /** The retention bound a replay directory carries when none is configured. */
    public const int DEFAULT_MAX_SNAPSHOTS = 1000;

    private string $directory;

    private int $maxSnapshots;

    /**
     * @param string $directory    the replay storage directory (validated by
     *                             {@see ReplayStorageDirectory})
     * @param int    $maxSnapshots the retention bound (>= 1): the directory
     *                             keeps at most this many snapshots
     *
     * @throws InvalidArgumentException when the directory or the bound is
     *                                  refused
     */
    public function __construct(string $directory, int $maxSnapshots = self::DEFAULT_MAX_SNAPSHOTS)
    {
        $this->directory = ReplayStorageDirectory::resolve($directory);
        $this->maxSnapshots = ReplayRetention::resolveBound($maxSnapshots);
    }

    /**
     * @throws JsonException when a snapshot cannot be encoded
     */
    public function save(
        ServerRequestInterface $serverRequest,
        Throwable $throwable,
        int $status,
        string $requestId,
    ): string {
        [$body, $bodyReplayable] = ReplayBodyCapture::capture($serverRequest);
        $path = $serverRequest->getUri()->getPath();
        if ($path === '' || preg_match('/[\x00-\x1F\x7F]/', $path) === 1 || !str_starts_with($path, '/')) {
            $path = '/';
        }

        $snapshot = [
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'request_id' => $requestId,
            'status' => $status,
            'exception_class' => $throwable::class,
            'method' => mb_strtoupper($serverRequest->getMethod()),
            'path' => $path,
            'query' => $this->query($serverRequest->getUri()->getQuery()),
            'headers' => $this->headers($serverRequest->getHeaders()),
            'body' => $body,
            'body_replayable' => $bodyReplayable,
        ];

        $id = bin2hex(random_bytes(16));
        $snapshot['id'] = $id;
        $json = json_encode($snapshot, \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE);

        ReplaySnapshotWriter::write($this->directory, $id, $json);
        ReplayRetention::prune($this->directory, $this->maxSnapshots);

        return $id;
    }

    /**
     * @param array<string, list<string>> $headers
     *
     * @return array<string, list<string>>
     */
    private function headers(array $headers): array
    {
        $safe = [];
        foreach ($headers as $name => $values) {
            if (preg_match('/authorization|cookie|secret|token|api[_-]?key|csrf/i', $name) === 1) {
                continue;
            }

            if (\in_array(mb_strtolower($name), ['host', 'content-length', 'connection', 'transfer-encoding'], true)) {
                continue;
            }

            $safe[$name] = array_map(
                static fn (string $value): string => mb_substr(RedactedMessage::of($value), 0, 512),
                \array_slice($values, 0, 10),
            );
        }

        return $safe;
    }

    private function query(string $query): string
    {
        if ($query === '' || mb_strlen($query) > 8192) {
            return '';
        }

        parse_str($query, $decoded);
        $safe = ReplayPayloadSanitizer::sanitize($decoded);

        return http_build_query(\is_array($safe) ? $safe : []);
    }
}
