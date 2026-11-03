<?php

declare(strict_types=1);

namespace Zef\Test\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\View\ViewException;

/**
 * Pins the lane's typed family posture: the refusal keeps the
 * message the throw site spelled, carries the internal status (500
 * -- the template name is the application's own addressing, never
 * the client's resource), and chains the cause it was constructed
 * over. The family membership itself (the framework root, the
 * status contract) is the type system's own fact -- the analyser
 * proves it, and a tautological assertion would only restate it.
 *
 * @internal
 */
#[CoversClass(ViewException::class)]
final class ViewExceptionTest extends TestCase
{
    public function testKeepsTheMessageTheThrowSiteSpelled(): void
    {
        $viewException = new ViewException('The view template \'about\' does not answer.');

        self::assertSame(
            'The view template \'about\' does not answer.',
            $viewException->getMessage(),
        );
    }

    public function testCarriesTheInternalHttpStatus(): void
    {
        $viewException = new ViewException('The view template \'about\' escapes it.');

        self::assertSame(500, $viewException->httpStatus());
    }

    public function testChainsTheCauseItWasConstructedOver(): void
    {
        $cause = new RuntimeException('boom');
        $viewException = new ViewException('The view template \'crash\' failed mid-render.', 0, $cause);

        self::assertSame($cause, $viewException->getPrevious());
    }
}
