<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

use Override;

/**
 * Raised when the uploads lane's writer refuses an upload whose client
 * media type the declared allowlist does not carry -- the type half of
 * the first writer's fail-closed validation (`uploads.types`), the 415
 * the error boundary renders as RFC 9457 problem+json.
 *
 * Carries the refusal's evidence: the media type the client sent (or
 * null when the upload declared none -- a type-less upload is refused,
 * never silently accepted against an allowlist it cannot be judged
 * against), and every media type the allowlist does accept, so the
 * renderer never re-derives what the comparison already resolved.
 *
 * The comparison is the tree's own media-type posture, exact: the
 * declared vocabulary's grammar demands the lowercase canonical
 * spelling (`image/png`, never `IMAGE/PNG`), and the client's answer
 * must already carry it -- the same case-exact match the accept
 * negotiation's own range fold keeps, so the first writer opens no
 * widening the request surface has not already spelled.
 *
 * @api the typed family's published refusal vocabulary: hosts catch this
 *      across their own code exactly like the 404/406 members beside it.
 */
final class UnsupportedMediaTypeException extends ZefException implements CarriesHttpStatus
{
    /**
     * The media type the client sent, or null when the upload
     * declared none.
     */
    public readonly ?string $clientMediaType;

    /**
     * Every media type the declared allowlist accepts, in
     * declaration order.
     *
     * @var list<non-empty-string>
     */
    public readonly array $acceptedMediaTypes;

    /**
     * @param mixed                   $clientMediaType    the media type the client sent, or null
     * @param array<array-key, mixed> $acceptedMediaTypes the declared allowlist
     *
     * @throws InvariantViolationException when the allowlist is empty or
     *                                     carries a non-string or empty entry
     */
    public function __construct(mixed $clientMediaType, array $acceptedMediaTypes)
    {
        $this->clientMediaType = \is_string($clientMediaType) ? $clientMediaType : null;
        $this->acceptedMediaTypes = $this->validated($acceptedMediaTypes);

        parent::__construct(
            $this->clientMediaType !== null
                ? "The uploaded file's media type '{$this->clientMediaType}' is not one the"
                    . ' uploads lane accepts (accepted: ' . implode(', ', $this->acceptedMediaTypes) . ').'
                : 'The uploaded file declares no media type (accepted: '
                    . implode(', ', $this->acceptedMediaTypes) . ').',
        );
    }

    #[Override]
    public function httpStatus(): int
    {
        return 415;
    }

    /**
     * Judges the allowlist every media-type refusal must carry: at least
     * one accepted type, every entry a non-empty string -- a writer that
     * accepts nothing is the wiring's own refusal, never this evidence.
     *
     * @param array<array-key, mixed> $acceptedMediaTypes
     *
     * @return list<non-empty-string>
     */
    private function validated(array $acceptedMediaTypes): array
    {
        if ($acceptedMediaTypes === []) {
            throw new InvariantViolationException(
                'An unsupported-media-type refusal must name at least one accepted media type.',
            );
        }

        $validated = [];
        foreach ($acceptedMediaTypes as $acceptedMediumType) {
            if (!\is_string($acceptedMediumType) || $acceptedMediumType === '') {
                throw new InvariantViolationException(
                    'An accepted media type must be a non-empty string, '
                    . get_debug_type($acceptedMediumType) . ' given.',
                );
            }

            $validated[] = $acceptedMediumType;
        }

        return $validated;
    }
}
