<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Zef\Framework\Exception\CarriesHttpStatus;

/**
 * The ingress budget's typed refusal: the request's body exceeds the
 * configured size limit (the 413 signal).
 *
 * The body-budget guards threw this refusal as a bare
 * {@see HttpException} whose message the status map had to recognise --
 * the message-sniffing the error-handling lane retires (ROADMAP.md,
 * "Error Handling System", phase 1): a signal that must reach a status
 * code is a class, not a string comparison. The refusal message stays
 * byte-identical to the throw sites the end-to-end tests pin, and the
 * class extends the layer's exception root so every existing
 * `catch (HttpException)` keeps catching it -- the fold's structural
 * catchability is preserved while the map gains its typed tier.
 */
final class PayloadTooLargeException extends HttpException implements CarriesHttpStatus
{
    private const string BODY_EXCEEDS_LIMIT = 'Request body exceeds configured size limit.';

    public function __construct()
    {
        parent::__construct(self::BODY_EXCEEDS_LIMIT);
    }

    #[Override]
    public function httpStatus(): int
    {
        return 413;
    }
}
