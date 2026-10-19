<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;

/**
 * @internal
 */
#[CoversClass(ErrorRequestContext::class)]
final class ErrorRequestContextTest extends TestCase
{
    public function testDatabaseAdapterCanIncrementOnlyTheActiveRequestCounter(): void
    {
        $serverRequest = new ServerRequest('GET', new Uri('https://app.example/users'));
        $errorRequestContext = new ErrorRequestContext('request-123');
        $requestWithContext = $serverRequest->withAttribute(ErrorRequestContext::ATTRIBUTE, $errorRequestContext);

        ErrorRequestContext::recordQueryFor($requestWithContext);
        ErrorRequestContext::recordQueryFor($requestWithContext);
        ErrorRequestContext::recordQueryFor($serverRequest);

        self::assertSame(2, $errorRequestContext->queryCount());
    }
}
