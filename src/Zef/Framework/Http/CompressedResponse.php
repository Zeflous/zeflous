<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * The compression family's answer fold: the response with its body
 * swapped for the compressed form, the coding declared, the stale
 * uncompressed length dropped, and the encoding line added to the
 * `Vary` tokens -- the one construction every compressed answer is.
 *
 * The stale `Content-Length` of the uncompressed form is dropped at
 * the response level: the emitter walks the body in chunks and
 * reconciles framing itself, and a response object whose declared
 * length matches its actual body is consistent for every downstream
 * consumer, the head-view doctrine replayed. The `Vary` decoration is
 * {@see CompressionVary}'s own walk, so a response the handler already
 * varied on keeps its tokens ahead of the encoding one.
 */
final class CompressedResponse
{
    /**
     * Answers the compressed form of one eligible response.
     *
     * @param ResponseInterface $response the eligible response
     * @param string            $coding   the negotiated content coding
     * @param int               $level    the zlib compression level
     */
    public static function answer(ResponseInterface $response, string $coding, int $level): ResponseInterface
    {
        $message = $response
            ->withBody(CompressedBody::compress($response->getBody(), $coding, $level))
            ->withHeader('Content-Encoding', $coding)
            ->withoutHeader('Content-Length')
        ;
        \assert($message instanceof ResponseInterface, 'the with* chain must preserve the response type.');

        return CompressionVary::merge($message);
    }
}
