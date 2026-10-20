<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Zef\Framework\Http\ErrorFaultContextFactory;
use Zef\Framework\Http\ErrorFaultLogWriter;
use Zef\Framework\Http\ErrorFaultSeverity;
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
        try {
            if ($status < 400) {
                return;
            }

            $level = ErrorFaultSeverity::for($exception, $status);
            ErrorFaultMetricsRecorder::record($this->meter, $exception, $status, $serverRequest);
            $context = ErrorFaultContextFactory::create($exception, $correlationId, $serverRequest);
            ErrorFaultLogWriter::write($this->logger, $level, $context);
        } catch (Throwable $throwable) {
            error_log('ZEF error recorder failure: ' . $throwable::class);
        }
    }
}
