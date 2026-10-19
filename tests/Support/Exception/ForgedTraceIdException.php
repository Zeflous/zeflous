<?php

declare(strict_types=1);

namespace Zef\Test\Support\Exception;

use Override;
use Zef\Framework\Exception\ZefException;

/**
 * A typed failure whose trace identifier is deliberately malformed: a
 * carriage return followed by a forged header line, the exact payload a
 * header-injection attempt would smuggle through a lazily validated
 * extension. The response lane must fall back to a freshly minted
 * correlation identifier instead of echoing it.
 */
final class ForgedTraceIdException extends ZefException
{
    #[Override]
    public function getTraceId(): string
    {
        return "invalid\r\nX-Forged: yes";
    }
}
