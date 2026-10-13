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

    public function testAllowsAnUppercasePhpTarget(): void
    {
        // The `php://` target is folded to lower case before it is matched
        // against the allow-list, so the permitted targets are case-insensitive.
        StreamScheme::assert('php://TEMP');
        StreamScheme::assert('PHP://Temp');

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

    public function testRefusesAnUppercaseTransport(): void
    {
        // The scheme is case-insensitive: `HTTP://` is the `http://` transport
        // and must be refused with the lower-cased scheme in the message.
        $this->assertRefused('HTTP://example.com/', "Refusing to open the 'http://' wrapper.");
    }

    public function testFoldsTheSchemeThroughMbstringCharacterSemantics(): void
    {
        // U+212A KELVIN SIGN lower-cases to ASCII "k" only under the multibyte
        // fold; a byte-wise fold leaves it non-ASCII, the scheme grammar fails
        // to match, and the whole string would be treated as a plain path. The
        // fold must therefore be the multibyte one, and the schemeful string
        // must be refused as an unknown wrapper.
        $this->assertRefused("\u{212A}://resource", "Refusing to open the unknown 'k://' wrapper.");
    }

    public function testFoldsThePhpTargetThroughMbstringCharacterSemantics(): void
    {
        // The `php://` target is folded with the same multibyte fold, so a
        // target that folds into a permitted one is accepted. U+212A folds to
        // "k", which is not a permitted target, so the refusal message must
        // carry the folded "k" -- a byte-wise fold would leave U+212A intact.
        $this->assertRefused("php://\u{212A}", "Refusing to open the 'php://k' target.");
    }

    public function testTreatsASchemelikeSubstringOfAPlainPathAsAPlainPath(): void
    {
        // A plain path that merely contains `://` is still a plain path: the
        // scheme must open the string, not appear anywhere inside it.
        StreamScheme::assert('/tmp/weird://x');

        $this->expectNotToPerformAssertions();
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
