<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\StreamInterface;

/**
 * The policed data transfer over the request body: guarded reads and the
 * whole-remainder drain, both under the ingress policy's budget.
 *
 * Extracted from the {@see LimitedInputStream} wrapper -- the same
 * depth-split the layer's stream engine uses to stay under the
 * architecture gate's difficulty budget (the unpoliced engine lives in
 * {@see StreamTransfer}; this is its policy-aware sibling). Every read
 * asks the inner stream for the budget's probe and refuses a delivered
 * chunk that crosses the limit; the drain repeats that in fixed-size
 * chunks and keeps the defensive break on the empty chunk -- the engine
 * contract that makes the loop terminate for a stream whose end-of-file
 * answer is missing or late.
 */
final class BodyTransfer
{
    /**
     * Reads up to the requested byte count without crossing the policy
     * limit: the inner stream is asked for the budget's probe, and a
     * delivered chunk that crosses the limit fails closed (and sticks).
     *
     * @param int $length the requested read length
     *
     * @throws HttpException when the length is negative or the delivered
     *                       chunk crosses the policy limit
     */
    public static function read(StreamInterface $stream, BodyBudget $bodyBudget, int $length): string
    {
        if ($length < 0) {
            throw new HttpException('Length must be non-negative.');
        }

        if ($length === 0) {
            return '';
        }

        $data = $stream->read($bodyBudget->probe($length));

        if ($bodyBudget->refuses($data)) {
            throw new HttpException('Request body exceeds configured size limit.');
        }

        return $data;
    }

    /**
     * Drains the stream's remaining bytes in policy-sized chunks, failing
     * closed the moment the total crosses the limit.
     */
    public static function contents(StreamInterface $stream, BodyBudget $bodyBudget): string
    {
        $out = '';
        while (!$stream->eof()) {
            $chunk = self::read($stream, $bodyBudget, 8192);
            if ($chunk === '') {
                break;
            }

            $out .= $chunk;
        }

        return $out;
    }
}
