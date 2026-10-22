<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Base class for framework exceptions, including a stable machine-readable
 * error code and optional developer-only context.
 *
 * The first three constructor arguments intentionally retain RuntimeException's
 * signature for backwards compatibility. Context is never exposed to clients
 * by default; the HTTP error renderer includes it only in explicitly enabled
 * debug responses after redaction.
 */
abstract class ZefException extends RuntimeException
{
    private readonly string $errorCode;

    private readonly ?string $traceId;

    /**
     * @param array<string, mixed> $details developer diagnostics; never public by default
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        string $errorCode = '',
        private readonly array $details = [],
        ?string $traceId = null,
    ) {
        parent::__construct($message, $code, $previous);

        $resolvedErrorCode = $errorCode !== '' ? $errorCode : ExceptionErrorCode::for(static::class);
        if (preg_match('/^[A-Z][A-Z0-9_]{0,63}$/D', $resolvedErrorCode) !== 1) {
            throw new InvalidArgumentException(
                'The error code must be 1-64 uppercase letters, digits, or underscores and start with a letter.',
            );
        }

        if ($traceId !== null && preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', $traceId) !== 1) {
            throw new InvalidArgumentException('The trace ID must be 1-128 ASCII token characters.');
        }

        $this->errorCode = $resolvedErrorCode;
        $this->traceId = $traceId;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed> */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function getTraceId(): ?string
    {
        return $this->traceId;
    }
}
