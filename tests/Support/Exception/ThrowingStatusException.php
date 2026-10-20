<?php

declare(strict_types=1);

namespace Zef\Test\Support\Exception;

use Override;
use RuntimeException;
use Zef\Framework\Exception\CarriesHttpStatus;
use Zef\Framework\Exception\ZefException;

/**
 * A typed failure whose status contract is broken at read time: the HTTP
 * status mapping consults {@see CarriesHttpStatus::httpStatus()} while
 * building the error response, and this shell answers that consultation
 * with a crash -- the boundary's mapping-failure fallback (500, the
 * generic problem envelope) is the behaviour under test.
 */
final class ThrowingStatusException extends ZefException implements CarriesHttpStatus
{
    #[Override]
    public function httpStatus(): int
    {
        throw new RuntimeException('status mapper failed');
    }
}
