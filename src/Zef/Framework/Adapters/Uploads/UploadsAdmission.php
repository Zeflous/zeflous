<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Uploads;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use Zef\Framework\Exception\UnsupportedMediaTypeException;
use Zef\Framework\Exception\UploadTooLargeException;
use Zef\Framework\Exception\ValidationException;

/**
 * The uploads lane's admission judgment: the fail-closed gates
 * every upload answers before the landing -- the four questions
 * the lane's own DoD names, asked in the order the evidence is
 * available, each refusal the typed family's request-surface
 * answer (CONTRIBUTING rule 17).
 *
 * THE ORDER IS THE HONESTY ITSELF: the transport's own error code
 * first (an errored upload's size and type are not trustworthy
 * answers, so the refusal names the error, never a misleading 413
 * or 415 over numbers the transport already refused), then the
 * size (the unmeasurable refusing as the honest 400 it is, never a
 * 413 that would name a number the engine does not hold -- #900's
 * "loses the field honestly" doctrine; the crossed ceiling
 * refusing 413 with the evidence carried), then the type (the
 * declared allowlist, matched exactly the tree's own media-type
 * posture keeps -- `RouteAcceptRange`'s case-exact comparison, no
 * widening: a typeless upload refuses the same way a refused type
 * does, both sides of the refusal carried).
 *
 * The fold exists because the archeology gate's difficulty ceiling
 * demanded it ({@see FileUploadsStore} -- the split that keeps each
 * half under the ceiling on its own terms, the fold-split
 * discipline the storage layout's own record spelled): this fold
 * owns the gates, the store owns the landing -- the name, the
 * move, the address -- and neither ever answers for the other.
 */
final readonly class UploadsAdmission
{
    /**
     * The declared size ceiling every upload is judged against.
     */
    private int $maxBytes;

    /**
     * The media-type allowlist the exact-match comparison keeps,
     * in declaration order.
     *
     * @var list<non-empty-string>
     */
    private array $types;

    /**
     * @param int                     $maxBytes the size ceiling (>= 1): every
     *                                          upload whose measured size crosses it is
     *                                          refused with the evidence carried
     * @param array<array-key, mixed> $types    the media-type allowlist --
     *                                          validated here to a non-empty list of
     *                                          non-empty strings, the grammar the
     *                                          declaration's own fold answers for the
     *                                          wiring's composition
     *
     * @throws InvalidArgumentException when the ceiling is below
     *                                  one byte, or the allowlist is empty or
     *                                  carries a non-string or empty entry
     */
    public function __construct(int $maxBytes, array $types)
    {
        if ($maxBytes < 1) {
            throw new InvalidArgumentException(
                'The uploads store ceiling must be at least one byte.',
            );
        }

        $validatedTypes = [];
        foreach ($types as $type) {
            if (!\is_string($type) || $type === '') {
                throw new InvalidArgumentException(
                    'The uploads store media types must be non-empty strings, '
                    . get_debug_type($type) . ' given.',
                );
            }

            $validatedTypes[] = $type;
        }

        if ($validatedTypes === []) {
            throw new InvalidArgumentException(
                'The uploads store must accept at least one media type.',
            );
        }

        $this->maxBytes = $maxBytes;
        $this->types = $validatedTypes;
    }

    /**
     * Judges the upload against every gate, refusing fail-closed.
     *
     * @param UploadedFileInterface $uploadedFile the uploaded file
     *                                            the request surface carries
     *
     * @throws ValidationException           when the upload carries an
     *                                       error code, or its size cannot be
     *                                       measured
     * @throws UploadTooLargeException       when the measured size crosses
     *                                       the declared ceiling
     * @throws UnsupportedMediaTypeException when the client's media type
     *                                       the allowlist does not carry
     */
    public function admit(UploadedFileInterface $uploadedFile): void
    {
        $error = $uploadedFile->getError();

        if ($error !== \UPLOAD_ERR_OK) {
            throw new ValidationException(
                "Uploaded file is not available (error {$error}).",
            );
        }

        $size = $uploadedFile->getSize();

        if ($size === null) {
            throw new ValidationException(
                'The uploaded file size cannot be measured, so it cannot'
                . ' be judged against the declared ceiling.',
            );
        }

        if ($size > $this->maxBytes) {
            throw new UploadTooLargeException($size, $this->maxBytes);
        }

        $clientMediaType = $uploadedFile->getClientMediaType();

        if (!\in_array($clientMediaType, $this->types, true)) {
            throw new UnsupportedMediaTypeException($clientMediaType, $this->types);
        }
    }
}
