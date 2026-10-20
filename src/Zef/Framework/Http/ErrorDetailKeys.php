<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/** The developer-detail entry-name gate: bounded, plain ASCII keys only. */
final class ErrorDetailKeys
{
    /**
     * @phpstan-assert-if-true string $key
     *
     * @psalm-assert-if-true string $key
     */
    public static function accepts(mixed $key): bool
    {
        return \is_string($key) && preg_match('/^[a-zA-Z0-9_.-]{1,80}$/D', $key) === 1;
    }
}
