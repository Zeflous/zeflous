<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ErrorRequestContext;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;

#[CoversClass(ErrorRequestContext::class)]
final class ErrorRequestContextTest extends TestCase
{
    public function testDatabaseAdapterCanIncrementOnlyTheActiveRequestCounter(): void
    {
        $request = new ServerRequest('GET', new Uri('https://app.example/users'));
        $context = new ErrorRequestContext('request-123');
        $requestWithContext = $request->withAttribute(ErrorRequestContext::ATTRIBUTE, $context);

        ErrorRequestContext::recordQueryFor($requestWithContext);
        ErrorRequestContext::recordQueryFor($requestWithContext);
        ErrorRequestContext::recordQueryFor($request);

        self::assertSame(2, $context->queryCount());
    }
}
