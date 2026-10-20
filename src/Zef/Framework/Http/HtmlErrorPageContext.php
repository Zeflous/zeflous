<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/** Immutable render data for the browser-facing error page. */
final readonly class HtmlErrorPageContext
{
    public function __construct(
        public int $status,
        public string $detail,
        public string $instance,
        public string $traceId,
        public bool $debug,
        public float $durationMilliseconds = 0.0,
        public int $queryCount = 0,
        public int $exceptionCount = 0,
    ) {
    }
}
