<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ErrorStatusMap;
use Zef\Framework\Http\HttpException;

/**
 * @internal
 */
#[CoversClass(ErrorStatusMap::class)]
final class ErrorStatusMapTest extends TestCase
{
    private const string BODY_TOO_LARGE = 'Request body exceeds configured size limit.';

    public function testTheBodysBudgetRefusalAnswersContentTooLarge(): void
    {
        self::assertSame(413, ErrorStatusMap::statusFor(new HttpException(self::BODY_TOO_LARGE)));
    }

    public function testEveryOtherLayerFailureAnswersBadRequest(): void
    {
        self::assertSame(400, ErrorStatusMap::statusFor(new HttpException('Stream detached.')));
        self::assertSame(400, ErrorStatusMap::statusFor(new HttpException('Invalid request target.')));
        self::assertSame(400, ErrorStatusMap::statusFor(new HttpException('')));
    }
}
