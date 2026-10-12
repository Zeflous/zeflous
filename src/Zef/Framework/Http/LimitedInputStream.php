<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * The request body under its ingress policy: a read-only stream that
 * refuses to deliver more bytes than the {@see RequestBodyPolicy} allows.
 *
 * This is the lazy half of the input validation gate (`ROADMAP.md`,
 * "Security & Hardening" -- max body size enforcement): the factory's eager
 * half judges the `Content-Length` claim before the body is even opened,
 * while this wrapper counts the bytes that actually arrive, so a lying,
 * absent or understated header cannot smuggle an oversized payload past
 * the adapter. A read that would cross the limit fails closed instead of
 * truncating, and the failure is sticky -- the poisoned budget keeps
 * refusing every later read, exactly like the reference implementation.
 *
 * The wrapper is a decorator, not a copy: every answer it does not own is
 * the inner stream's answer, `getSize()` reports the effective size (never
 * more than the policy allows, mirroring what the wrapper is willing to
 * deliver), and `write()` refuses outright -- a request body is an input,
 * never an output. Seeking rewinds the count, so a handler that reads,
 * rewinds and reads again is not double-charged for the same bytes. The
 * counting lives in the {@see BodyBudget} collaborator and the guarded
 * transfer in {@see BodyTransfer} -- the same depth-split the layer's
 * stream engine uses.
 *
 * Attribution: legacy `zef-framework` `Adapters\Http\LimitedInputStream`
 * (move-verbatim behaviour, folded onto the layer's single exception root
 * -- the legacy `PayloadTooLargeException` / `StreamNotWritableException` /
 * `InvalidArgumentException` signals all become `HttpException` with their
 * original message texts).
 */
final readonly class LimitedInputStream implements StreamInterface
{
    private BodyBudget $bodyBudget;

    /**
     * @param StreamInterface   $stream            the request body the SAPI delivered
     * @param RequestBodyPolicy $requestBodyPolicy the ingress policy the body serves
     */
    public function __construct(
        private StreamInterface $stream,
        RequestBodyPolicy $requestBodyPolicy,
    ) {
        $this->bodyBudget = new BodyBudget($requestBodyPolicy);
    }

    #[Override]
    public function __toString(): string
    {
        try {
            return $this->getContents();
        } catch (Throwable) {
            // PSR-7: __toString() must always answer a string, including on
            // the failure paths of the wrapped resource operations.
            return '';
        }
    }

    #[Override]
    public function close(): void
    {
        $this->stream->close();
    }

    #[Override]
    public function detach(): mixed
    {
        return $this->stream->detach();
    }

    /**
     * Answers the effective size: the inner stream's size clamped to the
     * policy limit, or null when the inner stream cannot measure itself
     * (the budget's own arithmetic, {@see BodyBudget::effectiveSize()}).
     *
     * PSR-7 consumers reason about how many bytes the stream can actually
     * deliver, and this wrapper refuses to deliver anything past the limit
     * -- an oversized body therefore reports exactly the limit. A read past
     * it throws rather than truncating, so the answer is an upper bound on
     * what `getContents()` will return for a compliant body.
     */
    #[Override]
    public function getSize(): ?int
    {
        return $this->bodyBudget->effectiveSize($this->stream->getSize());
    }

    #[Override]
    public function tell(): int
    {
        return $this->stream->tell();
    }

    #[Override]
    public function eof(): bool
    {
        return $this->stream->eof();
    }

    #[Override]
    public function isSeekable(): bool
    {
        return $this->stream->isSeekable();
    }

    /**
     * Seeks the inner stream and re-bases the budget on the new position,
     * so bytes before the cursor are not re-charged.
     */
    #[Override]
    public function seek(int $offset, int $whence = \SEEK_SET): void
    {
        $this->stream->seek($offset, $whence);
        $this->bodyBudget->rebase($this->stream->tell());
    }

    /**
     * Rewinds the inner stream and resets the budget, so a
     * read-rewind-read cycle is not double-charged for the same bytes.
     */
    #[Override]
    public function rewind(): void
    {
        $this->stream->rewind();
        $this->bodyBudget->reset();
    }

    /**
     * A request body is an input, never an output.
     */
    #[Override]
    public function isWritable(): bool
    {
        return false;
    }

    #[Override]
    public function write(string $string): int
    {
        throw new HttpException('Limited input stream is read-only.');
    }

    #[Override]
    public function isReadable(): bool
    {
        return $this->stream->isReadable();
    }

    /**
     * Reads up to the requested byte count without crossing the policy
     * limit: the guarded, policed read lives in the {@see BodyTransfer}
     * collaborator.
     */
    #[Override]
    public function read(int $length): string
    {
        return BodyTransfer::read($this->stream, $this->bodyBudget, $length);
    }

    /**
     * Drains the remaining bytes in policy-sized chunks, failing closed the
     * moment the total crosses the limit ({@see BodyTransfer}).
     */
    #[Override]
    public function getContents(): string
    {
        return BodyTransfer::contents($this->stream, $this->bodyBudget);
    }

    #[Override]
    public function getMetadata(?string $key = null): mixed
    {
        return $this->stream->getMetadata($key);
    }
}
