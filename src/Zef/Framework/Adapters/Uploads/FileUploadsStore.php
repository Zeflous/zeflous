<?php

declare(strict_types=1);

namespace Zef\Framework\Adapters\Uploads;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use Zef\Framework\Exception\UnsupportedMediaTypeException;
use Zef\Framework\Exception\UploadTooLargeException;
use Zef\Framework\Exception\ValidationException;
use Zef\Framework\Http\HttpException;

/**
 * The uploads lane's first writer: the fail-closed validation
 * engine that lands an uploaded file inside the audited `uploads`
 * lane -- the reservation three decision records carried
 * (KDD-SL3's census-moment doctrine, issue #722's own directive,
 * the #788 finding-9 honesty) closed by the pull request that
 * lands this class.
 *
 * THE ENGINE OWNS THE LANDING'S WHOLE GRAMMAR, the host never
 * spells a byte of it: every upload answers the two validations
 * the lane's own DoD names -- size (the declared ceiling, the
 * unmeasurable size refused as the honest validation error it is,
 * never a 413 that would name a number the engine does not hold)
 * and type (the declared allowlist, matched exactly against the
 * client's answer the tree's own media-type posture keeps) -- and
 * the landed name is the writer's own random draw, so no client
 * spelling -- filename, extension, path fragment -- ever reaches
 * a filesystem call. The move itself rides the PSR-7 interface's
 * own `moveTo()`: the uploaded-file machinery's tmp+rename dance
 * (a half-written destination can never appear under the landed
 * name) answers for the transfer, exactly as every host-side move
 * before this writer did.
 *
 * The refusals are the typed family's request-surface answers
 * (CONTRIBUTING rule 17): a crossed ceiling raises the 413
 * carrying its evidence, a refused type the 415 carrying both
 * sides, and the honest pre-flights -- an upload whose error code
 * the transport already refused, a size no stream can measure --
 * the 400 the validation error is. The error boundary renders
 * each as RFC 9457 problem+json; a host never sees a second error
 * shape to program against.
 *
 * The landed name's collision arithmetic: the rename's own POSIX
 * replace answers a 128-bit random draw that lands on an existing
 * name -- a probability the engine does not spend a branch on,
 * the same order of zero the identity-keyed lanes' replacement
 * semantics spell, with the draws' space 2^128 wide and the
 * landing's own exclusive-open sibling guarding every half-write.
 *
 * The constructor's own bounds are the Storage posture the
 * sister writers carry: the directory resolves through the lane's
 * own posture fold, the ceiling is at least one byte, and the
 * allowlist is a non-empty list of non-empty strings -- the
 * grammar the declaration's own fold answers for the wiring's
 * composition, re-checked here only as the engine's own shape
 * bound, so a store constructed by hand accepts at least one
 * media type and never the empty allowlist the wiring itself
 * refuses.
 *
 * @api the host's entry point: the controller-facing surface the
 *      `uploads.store` registration answers -- the same public-API
 *      posture the PSR-7 uploaded-file machinery's own `@api` move
 *      carries.
 */
final readonly class FileUploadsStore
{
    /**
     * The landed name's own entropy: sixteen random bytes spelled
     * as thirty-two hexadecimal characters, extension-less by
     * construction -- the uploads lane's names carry no client
     * spelling at all, and the type the validation already judged
     * is the host's own knowledge to serve.
     */
    private const int NAME_BYTES = 16;

    /**
     * The uploads lane's resolved canonical address.
     */
    private string $directory;

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
     * @param string                  $directory the uploads lane's root directory
     *                                           (validated by {@see UploadsStoreDirectory}:
     *                                           existing, writable, non-symlink, owner-only,
     *                                           outside the public document root)
     * @param int                     $maxBytes  the size ceiling (>= 1): every
     *                                           upload whose measured size crosses it is
     *                                           refused with the evidence carried
     * @param array<array-key, mixed> $types     the media-type allowlist the
     *                                           exact-match comparison keeps -- validated
     *                                           here to a non-empty list of non-empty
     *                                           strings, the grammar the declaration's own
     *                                           fold answers for the wiring's composition
     *
     * @throws InvalidArgumentException when the ceiling is below
     *                                  one byte, the allowlist is empty or carries a
     *                                  non-string or empty entry, or the directory
     *                                  refuses the posture
     */
    public function __construct(
        string $directory,
        int $maxBytes,
        array $types,
    ) {
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

        $this->directory = UploadsStoreDirectory::resolve($directory);
        $this->maxBytes = $maxBytes;
        $this->types = $validatedTypes;
    }

    /**
     * Validates the upload fail-closed and lands it inside the
     * uploads lane under the writer's own name.
     *
     * @param UploadedFileInterface $uploadedFile the uploaded file the
     *                                            request surface carries
     *
     * @return non-empty-string the landed file's absolute path
     *
     * @throws ValidationException           when the upload carries an
     *                                       error code, or its size cannot be measured
     * @throws UploadTooLargeException       when the measured size crosses the
     *                                       declared ceiling
     * @throws UnsupportedMediaTypeException when the client's media type
     *                                       the allowlist does not carry
     * @throws HttpException                 when any step of the
     *                                       PSR-7 move's tmp+rename dance fails
     */
    public function store(UploadedFileInterface $uploadedFile): string
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

        $path = $this->directory . \DIRECTORY_SEPARATOR . bin2hex(random_bytes(self::NAME_BYTES));

        $uploadedFile->moveTo($path);

        return $path;
    }
}
