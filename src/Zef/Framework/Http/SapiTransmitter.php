<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Closure;

/**
 * The SAPI transmission seam: the one place the HTTP layer writes a
 * response's status code and header fields to the SAPI output channel.
 *
 * The PHP CLI SAPI silently discards `header()` calls (verified against the
 * engine: `headers_list()` stays empty even while `headers_sent()` answers
 * false), so a test process can execute the real transmission branch but
 * can never observe what it would have shipped. The constructor-accepted
 * sinks are the observation point: a recording closure turns the void SAPI
 * write into a value, which keeps the emission call sites mutant-honest
 * (the replace flag, the field-line spelling and the transmitted status
 * all become assertable) without growing any test-only surface on the
 * layer itself. The real branches stay plain engine calls with no literal
 * operands, so they carry no mutants of their own to kill.
 *
 * The default-constructed transmitter transmits for real: `header()` with
 * the caller's replace flag and `http_response_code()` with the caller's
 * status. `sent()` answers the SAPI's already-sent question, which guards
 * the whole emission block -- the PHPUnit process keeps an output buffer
 * open, so the real answer stays false there and the genuine branch runs.
 */
final readonly class SapiTransmitter
{
    /**
     * @param null|Closure(string, bool): void $fieldSink  records (or
     *                                                     otherwise observes) a header field line and its replace
     *                                                     flag instead of the SAPI `header()` call
     * @param null|Closure(int): void          $statusSink records the status
     *                                                     code instead of the SAPI `http_response_code()` call
     * @param null|Closure(): bool             $sentSink   answers the
     *                                                     headers-already-sent question instead of the SAPI itself
     */
    public function __construct(
        private ?Closure $fieldSink = null,
        private ?Closure $statusSink = null,
        private ?Closure $sentSink = null,
    ) {
    }

    /**
     * Whether the SAPI has already shipped its headers, which makes any
     * further status or field transmission a no-op for the response.
     */
    public function sent(): bool
    {
        return $this->sentSink instanceof Closure ? ($this->sentSink)() : headers_sent();
    }

    /**
     * Transmits the response's status code to the SAPI.
     */
    public function status(int $code): void
    {
        if ($this->statusSink instanceof Closure) {
            ($this->statusSink)($code);
        } else {
            http_response_code($code);
        }
    }

    /**
     * Transmits one header field line to the SAPI.
     *
     * @param string $line    the full field line, name and value joined
     *                        by the ": " separator
     * @param bool   $replace whether the field replaces a same-named
     *                        earlier field (false appends, which is how a
     *                        multi-valued PSR-7 header reaches the client)
     */
    public function field(string $line, bool $replace): void
    {
        if ($this->fieldSink instanceof Closure) {
            ($this->fieldSink)($line, $replace);
        } else {
            header($line, $replace);
        }
    }
}
