<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The parsed-body guard: PSR-7 allows the deserialized body to be an
 * array, an object or null -- anything else (a scalar) means the body
 * was never deserialized into a shape the contract describes, which is
 * rejected fail-closed with the layer's exception, byte-identical to
 * the reference implementation (legacy `zef-framework`
 * `ServerRequest` constructor). Pure static helper.
 */
final class ParsedBody
{
    /**
     * @throws HttpException when the parsed body is not an array, object
     *                       or null
     */
    public static function assert(mixed $parsedBody): void
    {
        if (!\is_array($parsedBody) && !\is_object($parsedBody) && $parsedBody !== null) {
            throw new HttpException('Parsed body must be array, object or null.');
        }
    }
}
