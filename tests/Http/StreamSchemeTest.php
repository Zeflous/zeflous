<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\StreamScheme;
use Zef\Test\Support\Http\NullStreamWrapper;

/**
 * @internal
 */
#[CoversClass(StreamScheme::class)]
final class StreamSchemeTest extends TestCase
{
    private const string USERLAND_PROTOCOL = 'zef-scheme-probe';

    #[Override]
    public static function setUpBeforeClass(): void
    {
        stream_wrapper_register(self::USERLAND_PROTOCOL, NullStreamWrapper::class);
    }

    #[Override]
    public static function tearDownAfterClass(): void
    {
        stream_wrapper_unregister(self::USERLAND_PROTOCOL);
    }

    public function testAllowsAPlainFilesystemPath(): void
    {
        StreamScheme::assert('/tmp/zef-stream-probe');

        $this->expectNotToPerformAssertions();
    }

    public function testAllowsTheFileScheme(): void
    {
        StreamScheme::assert('file:///tmp/zef-stream-probe');

        $this->expectNotToPerformAssertions();
    }

    public function testAllowsThePhpTempTarget(): void
    {
        StreamScheme::assert('php://temp');

        $this->expectNotToPerformAssertions();
    }

    public function testAllowsThePhpInputTarget(): void
    {
        StreamScheme::assert('php://input');

        $this->expectNotToPerformAssertions();
    }

    public function testRefusesARemoteTransport(): void
    {
        $this->assertRefused('http://example.com/', "Refusing to open the 'http://' wrapper.");
    }

    public function testRefusesThePharWrapper(): void
    {
        $this->assertRefused('phar:///tmp/archive.phar/file', "Refusing to open the 'phar://' wrapper.");
    }

    public function testRefusesTheDataWrapper(): void
    {
        $this->assertRefused('data://text/plain;base64,aGk=', "Refusing to open the 'data://' wrapper.");
    }

    public function testRefusesAnUnknownPhpTarget(): void
    {
        $this->assertRefused(
            'php://filter/read=convert.base64-encode/resource=/etc/passwd',
            "Refusing to open the 'php://filter' target.",
        );
    }

    public function testAllowsARegisteredUserlandWrapper(): void
    {
        StreamScheme::assert(self::USERLAND_PROTOCOL . '://resource');

        $this->expectNotToPerformAssertions();
    }

    public function testRefusesAnUnknownScheme(): void
    {
        $this->assertRefused('zef-unknown://resource', "Refusing to open the unknown 'zef-unknown://' wrapper.");
    }

    /**
     * @param non-empty-string $uri
     */
    private function assertRefused(string $uri, string $message): void
    {
        try {
            StreamScheme::assert($uri);
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }
}
