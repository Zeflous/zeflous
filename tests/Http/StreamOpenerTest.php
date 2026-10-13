<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\StreamOpener;

/**
 * @internal
 */
#[CoversClass(StreamOpener::class)]
final class StreamOpenerTest extends TestCase
{
    public function testOpensThePhpTempTarget(): void
    {
        $resource = StreamOpener::open('php://temp', 'r+b');

        self::assertIsResource($resource);

        fclose($resource);
    }

    public function testRefusesARemoteTransportBeforeOpening(): void
    {
        // The scheme guard must run before the engine is reached: the refusal
        // has to come from the guard, not from a failed open.
        try {
            StreamOpener::open('http://example.com/', 'r');
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame("Refusing to open the 'http://' wrapper.", $httpException->getMessage());
        }
    }

    public function testRefusesAnUnknownPhpTargetBeforeOpening(): void
    {
        try {
            StreamOpener::open('php://filter/read=convert.base64-encode/resource=/etc/passwd', 'r');
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame("Refusing to open the 'php://filter' target.", $httpException->getMessage());
        }
    }

    public function testReportsAFailedOpenAsAnHttpException(): void
    {
        $missing = sys_get_temp_dir() . '/zef-missing-' . bin2hex(random_bytes(8));

        $this->expectException(HttpException::class);

        StreamOpener::open($missing, 'r');
    }
}
