<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A PSR-15 middleware that records the fact it entered the pipeline:
 * one append write to a marker file, the cheapest observation a test
 * can make about WHEN a boot refused -- before the pipeline ever ran,
 * or from inside it. The HTTP inspection boot's own doctrine test
 * rides it (a defective route table must refuse at the boot seam,
 * never from inside a pipeline that already started).
 *
 * @internal
 */
final class FileEntryMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $markerFile,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        file_put_contents($this->markerFile, "entered\n", \FILE_APPEND);

        return $handler->handle($request);
    }
}
