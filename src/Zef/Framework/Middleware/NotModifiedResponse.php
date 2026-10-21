<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use UnexpectedValueException;

/**
 * Builds the bodyless 304 a conditional request earns: the status is set
 * and the body replaced with an empty stream, exactly once per response.
 *
 * @internal
 */
final readonly class NotModifiedResponse
{
    public function __construct(private StreamFactoryInterface $streamFactory)
    {
    }

    public function from(ResponseInterface $response): ResponseInterface
    {
        $message = $response->withStatus(304)->withBody($this->streamFactory->createStream(''));
        if (!$message instanceof ResponseInterface) {
            throw new UnexpectedValueException(
                'The conditional response operation did not return a ResponseInterface.',
            );
        }

        return $message;
    }
}
