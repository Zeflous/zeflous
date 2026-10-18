<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Zef\Framework\Telemetry\MeterInterface;

/** Records request failures through the logging and metrics boundaries. */
final readonly class ErrorFaultRecorder
{
    public function __construct(
        private ?LoggerInterface $logger = null,
        private ?MeterInterface $meter = null,
    ) {
    }

    public function record(
        Throwable $exception,
        int $status,
        string $correlationId,
        ServerRequestInterface $serverRequest,
    ): void {
        if ($status < 400) {
            return;
        }

        $level = ErrorFaultSeverity::for($exception, $status);
        ErrorFaultMetricsRecorder::record($this->meter, $exception, $status, $serverRequest);
        $context = ErrorFaultContextFactory::create($exception, $correlationId, $serverRequest);
        ErrorFaultLogWriter::write($this->logger, $level, $context);
    }
}
