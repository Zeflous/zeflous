<?php

declare(strict_types=1);

namespace Zef\Test\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Exception\CarriesHttpStatus;
use Zef\Framework\Exception\ZefException;
use Zef\Framework\View\ViewException;

/**
 * Pins the lane's typed family posture: the refusal travels the
 * framework root, carries the internal status (500 -- the template
 * name is the application's own addressing, never the client's
 * resource), and keeps the message the throw site spelled.
 *
 * @internal
 */
#[CoversClass(ViewException::class)]
final class ViewExceptionTest extends TestCase
{
    public function testTravelsTheFrameworkRoot(): void
    {
        $viewException = new ViewException('The view template \'about\' does not answer.');

        self::assertInstanceOf(ZefException::class, $viewException);
        self::assertSame(
            'The view template \'about\' does not answer.',
            $viewException->getMessage(),
        );
    }

    public function testCarriesTheInternalHttpStatus(): void
    {
        $viewException = new ViewException('The view template \'about\' escapes it.');

        self::assertInstanceOf(CarriesHttpStatus::class, $viewException);
        self::assertSame(500, $viewException->httpStatus());
    }

    public function testChainsTheCauseItWasConstructedOver(): void
    {
        $cause = new \RuntimeException('boom');
        $viewException = new ViewException('The view template \'crash\' failed mid-render.', 0, $cause);

        self::assertSame($cause, $viewException->getPrevious());
    }
}
