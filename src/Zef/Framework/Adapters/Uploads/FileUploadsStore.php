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
 * The uploads lane's first writer: the landing engine that puts
 * a validated upload inside the audited `uploads` lane -- the
 * reservation three decision records carried (KDD-SL3's
 * census-moment doctrine, issue #722's own directive, the #788
 * finding-9 honesty) closed by the pull request that lands this
 * class.
 *
 * THE ENGINE OWNS THE LANDING'S WHOLE GRAMMAR, the host never
 * spells a byte of it: every upload answers the admission
 * judgment first ({@see UploadsAdmission} -- the fail-closed
 * size and type gates the lane's own DoD names, the fold the
 * archeology gate's difficulty ceiling demanded so each half
 * answers under the ceiling on its own terms), then the landed
 * name is the writer's own random draw, so no client spelling --
 * filename, extension, path fragment -- ever reaches a filesystem
 * call. The move itself rides the PSR-7 interface's own
 * `moveTo()`: the uploaded-file machinery's tmp+rename dance (a
 * half-written destination can never appear under the landed
 * name) answers for the transfer, exactly as every host-side move
 * before this writer did.
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
 * own posture fold, and the admission owns the ceiling and the
 * allowlist -- the store composed by hand accepts at least one
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
     * spelling at all, and the type the admission already judged
     * is the host's own knowledge to serve.
     */
    private const int NAME_BYTES = 16;

    /**
     * The uploads lane's resolved canonical address.
     */
    private string $directory;

    /**
     * The admission judgment the store's landing answers to.
     */
    private UploadsAdmission $uploadsAdmission;

    /**
     * @param string                  $directory the uploads lane's root directory
     *                                           (validated by {@see UploadsStoreDirectory}:
     *                                           existing, writable, non-symlink, owner-only,
     *                                           outside the public document root)
     * @param int                     $maxBytes  the size ceiling (>= 1): every
     *                                           upload whose measured size crosses it is
     *                                           refused with the evidence carried
     * @param array<array-key, mixed> $types     the media-type allowlist the
     *                                           exact-match comparison keeps
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
        $this->directory = UploadsStoreDirectory::resolve($directory);
        $this->uploadsAdmission = new UploadsAdmission($maxBytes, $types);
    }

    /**
     * Admits the upload through the fail-closed gates and lands it
     * inside the uploads lane under the writer's own name.
     *
     * @param UploadedFileInterface $uploadedFile the uploaded file the
     *                                            request surface carries
     *
     * @return non-empty-string the landed file's absolute path
     *
     * @throws ValidationException           when the upload carries an error
     *                                       code, or its size cannot be measured
     * @throws UploadTooLargeException       when the measured size crosses the
     *                                       declared ceiling
     * @throws UnsupportedMediaTypeException when the client's media type
     *                                       the allowlist does not carry
     * @throws HttpException                 when any step of the PSR-7 move's
     *                                       tmp+rename dance fails
     */
    public function store(UploadedFileInterface $uploadedFile): string
    {
        $this->uploadsAdmission->admit($uploadedFile);

        $path = $this->directory . \DIRECTORY_SEPARATOR . bin2hex(random_bytes(self::NAME_BYTES));

        $uploadedFile->moveTo($path);

        return $path;
    }
}
