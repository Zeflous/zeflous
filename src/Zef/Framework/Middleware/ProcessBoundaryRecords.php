<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
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
 * through the request-facts memo ({@see RequestFactsMemo}): the
 * source is read once per window and every record of the window rides
 * the same identifier, so one request's diagnostics correlate with
 * each other (the per-record mint the lane carried before fragmented
 * them whenever the request claimed no `X-Request-ID`). The window is
 * the classic model the default source presupposes -- one process,
 * one request; a persistent worker resets it
 * ({@see self::freshRequest()}) at each request's start, and each
 * installation of the boundary opens a fresh window itself.
 *
 * The write half carries the two records the arms leave: a native
 * diagnostic at the severity the diagnostic mapped to, and an
 * uncaught fatal at critical -- both through the JSONL lane's writer,
 * with the named message that keeps a stored record's origin readable
 * without its context. The diagnostic arm folds the repeat storm
 * through the ledger ({@see DiagnosticRepeats}): one record for the
 * first occurrence, a counted silence for its repeats, the summary
 * record that names the suppressed count -- never the unbounded
 * rebuild-per-diagnostic the lane carried before.
 */
final readonly class ProcessBoundaryRecords
{
    /** @var Closure(): ServerRequestInterface */
    private Closure $requestSource;

    private RequestFactsMemo $requestFactsMemo;

    private DiagnosticRepeats $diagnosticRepeats;

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
        $this->requestFactsMemo = new RequestFactsMemo();
        $this->diagnosticRepeats = new DiagnosticRepeats();
    }

    /**
     * The request facts an arm's record and answer both read: the
     * request itself and its correlation identifier -- read once per
     * window, the same identifier for every record of the window.
     *
     * @return array{request: ServerRequestInterface, correlation: string} the facts
     */
    public function facts(): array
    {
        return $this->requestFactsMemo->facts($this->requestSource);
    }

    /**
     * Opens a fresh facts window: the next facts read observes the
     * request the source answers then, and the repeat ledger starts
     * over. The classic SAPI never needs this -- its process answers
     * one request; a persistent worker that keeps one boundary across
     * requests calls it at each request's start.
     */
    public function freshRequest(): void
    {
        $this->requestFactsMemo->forget();
        $this->diagnosticRepeats->reset();
    }

    /**
     * Records one native diagnostic at the severity the diagnostic
     * mapped to -- the first occurrence of its signature verbatim, a
     * suppressed repeat inside the cadence nothing, and the cadence's
     * summary record with the suppressed count named.
     *
     * @param non-empty-string $level   the PSR-3 level the diagnostic mapped to
     * @param string           $message the engine's diagnostic text
     * @param string           $file    the file the diagnostic names
     * @param int              $line    the line the diagnostic names
     */
    public function diagnostic(string $level, string $message, string $file, int $line): void
    {
        $annotated = $this->diagnosticRepeats->verdict($level, $message, $file, $line);

        if ($annotated === null) {
            return;
        }

        $facts = $this->facts();

        ErrorFaultLogWriter::write(
            $this->logger,
            $level,
            DiagnosticContextFactory::create(
                $level,
                $annotated,
                $file,
                $line,
                $facts['correlation'],
                $facts['request'],
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
