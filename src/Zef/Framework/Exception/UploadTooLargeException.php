<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use Override;

/**
 * Raised when the uploads lane's writer refuses an upload whose measured
 * size crosses the declared ceiling -- the size half of the first writer's
 * fail-closed validation (`uploads.max_bytes`), the 413 the error
 * boundary renders as RFC 9457 problem+json.
 *
 * Carries the refusal's evidence: the measured size and the ceiling it
 * crossed, so the renderer and the host's own catch site never re-derive
 * what the comparison already resolved. The evidence is validated here,
 * the value-object discipline the typed family keeps everywhere: a
 * negative size is a defect (the PSR-7 uploaded file's own constructor
 * refuses one), and a ceiling below one byte is the same (the knob's own
 * integer seam refuses it first, so an exception carrying one is a
 * construct-time contract break, not an operator mistake).
 *
 * The unmeasurable upload never reaches this refusal: a size the stream
 * cannot answer refuses as the honest validation error (400) it is -- the
 * same doctrine the head-answer lane spelled for the unmeasurable cursor
 * ("loses the field honestly"), never a 413 that would name a number the
 * engine does not hold.
 *
 * @api the typed family's published refusal vocabulary: hosts catch this
 *      across their own code exactly like the 404/406 members beside it.
 */
final class UploadTooLargeException extends ZefException implements CarriesHttpStatus
{
    /**
     * The upload's measured size in bytes.
     */
    public readonly int $size;

    /**
     * The declared ceiling in bytes the size crossed.
     */
    public readonly int $maxBytes;

    /**
     * @param int $size     the upload's measured size (>= 0)
     * @param int $maxBytes the declared ceiling (>= 1)
     *
     * @throws InvariantViolationException when the size is negative or the
     *                                     ceiling below one byte
     */
    public function __construct(int $size, int $maxBytes)
    {
        if ($size < 0) {
            throw new InvariantViolationException(
                'An upload-size refusal must carry a non-negative size.',
            );
        }

        if ($maxBytes < 1) {
            throw new InvariantViolationException(
                'An upload-size refusal must carry a ceiling of at least one byte.',
            );
        }

        $this->size = $size;
        $this->maxBytes = $maxBytes;

        parent::__construct(
            "The uploaded file's {$size} bytes exceed the {$maxBytes}-byte ceiling.",
        );
    }

    #[Override]
    public function httpStatus(): int
    {
        return 413;
    }
}
