<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Zef\Framework\Http\ResponseEmitter;

/**
 * The process boundary's transmission fold: the SAPI verdict and the
 * shipping lane the boundary's three arms answer to -- everything the
 * process's own spelling decides about where an answer goes.
 *
 * The verdict half answers the one question every arm asks first
 * ({@see self::answersHttp()}): the SAPI spellings that answer no HTTP
 * client at all. The shipping half moves one answer to whichever
 * consumer that verdict names -- the client through the emitter when
 * the headers have not shipped, the CLI operator through the bounded
 * sink line -- and the caller owns the refusal's own naming, so each
 * arm's "could not answer" message stays the arm's own words.
 *
 * The constructor-accepted closures are the observation points the
 * boundary's tests drive ({@see \Zef\Framework\Http\SapiTransmitter}'s
 * sink doctrine): the emission, the CLI sink and the headers probe,
 * the defaults being the real engine calls, exactly what a booted
 * process runs.
 */
final readonly class ProcessBoundaryTransmission
{
    /** The SAPI spellings that answer no HTTP client. */
    private const array CLI_SAPI_SPELLINGS = ['cli', 'phpdbg', 'embed'];

    /** The bounded length of one sink line's message head. */
    private const int SINK_MESSAGE_BOUND = 120;

    /** @var Closure(ResponseInterface): void */
    private Closure $responseEmitter;

    /** @var Closure(string): void */
    private Closure $diagnosticSink;

    /** @var Closure(): bool */
    private Closure $headersSentProbe;

    private string $sapi;

    /**
     * @param null|Closure(ResponseInterface): void $responseEmitter  ships an answer to
     *                                                                the client; null emits
     *                                                                through the HTTP layer's
     *                                                                own emitter
     * @param null|Closure(string): void            $diagnosticSink   receives the bounded
     *                                                                CLI diagnostic line; null
     *                                                                routes it through the SAPI
     *                                                                error log -- the CLI
     *                                                                operator's own channel
     * @param null|Closure(): bool                  $headersSentProbe answers whether the
     *                                                                SAPI already shipped its
     *                                                                headers; null asks the
     *                                                                engine
     * @param null|string                           $sapi             the SAPI spelling the
     *                                                                verdict branches on; null
     *                                                                reads the running process's
     *                                                                own
     */
    public function __construct(
        ?Closure $responseEmitter = null,
        ?Closure $diagnosticSink = null,
        ?Closure $headersSentProbe = null,
        ?string $sapi = null,
    ) {
        $this->sapi = $sapi ?? \PHP_SAPI;
        $this->responseEmitter = $responseEmitter ?? static function (ResponseInterface $response): void {
            new ResponseEmitter()->emit($response);
        };
        $this->diagnosticSink = $diagnosticSink ?? static function (string $line): void {
            error_log($line);
        };
        $this->headersSentProbe = $headersSentProbe ?? static fn (): bool => headers_sent();
    }

    /**
     * Whether the running SAPI answers an HTTP client -- the fold every
     * arm's answer branches on, and the meter's own honesty: a CLI
     * process has no HTTP semantics to count.
     */
    public function answersHttp(): bool
    {
        return !\in_array($this->sapi, self::CLI_SAPI_SPELLINGS, true);
    }

    /**
     * Ships one answer to the client through the emitter.
     *
     * @param ResponseInterface $response the answer an arm mapped to
     *
     * @return bool whether the answer shipped; false names the
     *              headers-already-sent refusal the caller owns
     */
    public function ship(ResponseInterface $response): bool
    {
        if (($this->headersSentProbe)()) {
            return false;
        }

        ($this->responseEmitter)($response);

        return true;
    }

    /**
     * Answers the CLI operator through the bounded sink line.
     *
     * @param string $origin  the line's origin label
     * @param string $message the message the line carries
     */
    public function sink(string $origin, string $message): void
    {
        ($this->diagnosticSink)($this->sinkLine($origin, $message));
    }

    /**
     * The bounded, control-byte-stripped line the CLI sink carries -- the
     * report display's own hygiene, so a stored escape sequence can never
     * ride a boundary line into the operator's terminal.
     *
     * @param string $origin  the line's origin label
     * @param string $message the message the line carries
     *
     * @return non-empty-string the sink line
     */
    private function sinkLine(string $origin, string $message): string
    {
        $stripped = preg_replace('/[\x00-\x1F\x7F]/', '', mb_substr($message, 0, self::SINK_MESSAGE_BOUND));
        \assert(\is_string($stripped));

        $head = $stripped !== '' ? $stripped : 'no message';

        return 'zef: ' . $origin . ': ' . $head;
    }
}
