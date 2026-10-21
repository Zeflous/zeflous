<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Zef\Framework\Http\CorrelationId;
use Zef\Framework\Http\DiagnosticContextFactory;
use Zef\Framework\Http\ErrorFaultLogWriter;
use Zef\Framework\Http\RequestFactory;

/**
 * The process boundary's record fold: the request facts and the
 * diagnostic writes every arm answers to -- one place where the
 * bounded, redacted, privacy-by-default fault record is composed from
 * the request the arms' facts synthesise from.
 *
 * The facts half answers the request and its correlation identifier
 * (the request source being the observation point the lane's tests
 * drive; the default reads the SAPI superglobals, exactly what a
 * booted process runs). The write half carries the two records the
 * arms leave: a native diagnostic at the severity the diagnostic
 * mapped to, and an uncaught fatal at critical -- both through the
 * JSONL lane's writer, with the named message that keeps a stored
 * record's origin readable without its context.
 */
final readonly class ProcessBoundaryRecords
{
    /** @var Closure(): ServerRequestInterface */
    private Closure $requestSource;

    /**
     * @param null|LoggerInterface                   $logger        the PSR-3 sink the
     *                                                              records write through;
     *                                                              null routes them to the
     *                                                              SAPI error log
     * @param null|Closure(): ServerRequestInterface $requestSource answers the
     *                                                              request the records'
     *                                                              facts synthesise from;
     *                                                              null reads the SAPI
     *                                                              superglobals
     */
    public function __construct(
        private ?LoggerInterface $logger,
        ?Closure $requestSource = null,
    ) {
        $this->requestSource = $requestSource ?? static fn (): ServerRequestInterface => RequestFactory::fromGlobals();
    }

    /**
     * The request facts an arm's record and answer both read: the
     * request itself and its correlation identifier.
     *
     * @return array{request: ServerRequestInterface, correlation: string} the facts
     */
    public function facts(): array
    {
        $serverRequest = ($this->requestSource)();

        return ['request' => $serverRequest, 'correlation' => CorrelationId::for($serverRequest)];
    }

    /**
     * Records one native diagnostic at the severity the diagnostic
     * mapped to.
     *
     * @param non-empty-string $level   the PSR-3 level the diagnostic mapped to
     * @param string           $message the engine's diagnostic text
     * @param string           $file    the file the diagnostic names
     * @param int              $line    the line the diagnostic names
     */
    public function diagnostic(string $level, string $message, string $file, int $line): void
    {
        $serverRequest = ($this->requestSource)();

        ErrorFaultLogWriter::write(
            $this->logger,
            $level,
            DiagnosticContextFactory::create(
                $level,
                $message,
                $file,
                $line,
                CorrelationId::for($serverRequest),
                $serverRequest,
            ),
            'Native diagnostic',
        );
    }

    /**
     * Records one witnessed fatal at critical.
     *
     * @param ProcessBoundaryFatals                                       $processBoundaryFatals the witnessed
     *                                                                                           fatal's facts
     * @param array{request: ServerRequestInterface, correlation: string} $facts                 the facts
     *                                                                                           the arm already
     *                                                                                           synthesised
     */
    public function fatal(ProcessBoundaryFatals $processBoundaryFatals, array $facts): void
    {
        ErrorFaultLogWriter::write(
            $this->logger,
            ProcessBoundaryFatals::SEVERITY,
            DiagnosticContextFactory::create(
                ProcessBoundaryFatals::SEVERITY,
                $processBoundaryFatals->message,
                $processBoundaryFatals->file,
                $processBoundaryFatals->line,
                $facts['correlation'],
                $facts['request'],
            ),
            'Uncaught fatal',
        );
    }
}
