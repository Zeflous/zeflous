<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Uri;
use Zef\Framework\Http\UriAuthority;
use Zef\Framework\Http\UriEncoder;
use Zef\Framework\Http\UriGrammar;
use Zef\Framework\Http\UriHostGrammar;
use Zef\Framework\Http\UriParser;
use Zef\Framework\Http\UriString;

/**
 * The with* protocol lives in the UriMutations trait; PHPUnit cannot target a
 * trait for coverage, so its methods count under the composing Uri class.
 *
 * @internal
 */
#[CoversClass(Uri::class)]
#[CoversClass(UriAuthority::class)]
#[CoversClass(UriEncoder::class)]
#[CoversClass(UriGrammar::class)]
#[CoversClass(UriHostGrammar::class)]
#[CoversClass(UriParser::class)]
#[CoversClass(UriString::class)]
final class UriTest extends TestCase
{
    public function testParsesAndRebuildsEveryComponent(): void
    {
        $uri = new Uri('https://user:pass@host.example:8443/path/to;x?a=1&b=2#frag');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('host.example', $uri->getHost());
        self::assertSame(8443, $uri->getPort());
        self::assertSame('/path/to;x', $uri->getPath());
        self::assertSame('a=1&b=2', $uri->getQuery());
        self::assertSame('frag', $uri->getFragment());
        self::assertSame('user:pass@host.example:8443', $uri->getAuthority());
        self::assertSame('https://user:pass@host.example:8443/path/to;x?a=1&b=2#frag', (string) $uri);
    }

    public function testTheEmptyUriIsEmptyEverywhere(): void
    {
        $uri = new Uri('');

        self::assertSame('', $uri->getScheme());
        self::assertSame('', $uri->getUserInfo());
        self::assertSame('', $uri->getHost());
        self::assertNull($uri->getPort());
        self::assertSame('', $uri->getPath());
        self::assertSame('', $uri->getQuery());
        self::assertSame('', $uri->getFragment());
        self::assertSame('', $uri->getAuthority());
        self::assertSame('', (string) $uri);
    }

    public function testParsesSchemelessOriginFormUris(): void
    {
        $uri = new Uri('//host.example/path?q#f');

        self::assertSame('', $uri->getScheme());
        self::assertSame('host.example', $uri->getHost());
        self::assertSame('/path', $uri->getPath());
        self::assertSame('//host.example/path?q#f', (string) $uri);
    }

    public function testParsesRelativePaths(): void
    {
        $uri = new Uri('a/relative/path');

        self::assertSame('a/relative/path', $uri->getPath());
        self::assertSame('', $uri->getAuthority());
        self::assertSame('a/relative/path', (string) $uri);
    }

    public function testRejectsUnparsableUris(): void
    {
        $this->assertThrows("Unable to parse URI 'http://h:65536/'.", static function (): void {
            new Uri('http://h:65536/');
        });
    }

    public function testRejectsInvalidSchemes(): void
    {
        $this->assertThrows('Invalid URI scheme.', static function (): void {
            new Uri('1http://host.example/');
        });
        $this->assertThrows('Invalid URI scheme.', static function (): void {
            new Uri('https://host.example')->withScheme('1http');
        });
    }

    public function testSchemeIsLowercased(): void
    {
        $uri = new Uri('https://host.example')->withScheme('HTTPS');

        self::assertSame('https', $uri->getScheme());
    }

    public function testSchemeAndHostFoldThroughTheFullUnicodeCaseMap(): void
    {
        $uri = new Uri('https://host.example');

        // U+212A KELVIN SIGN lower-cases to the ASCII "k" under the Unicode
        // case map; a byte-wise fold would leave the three raw bytes behind
        // and the grammar would reject both components outright.
        self::assertSame('httpk', $uri->withScheme("HTTP\u{212A}")->getScheme());
        self::assertSame('kelvin.example', $uri->withHost("\u{212A}ELVIN.example")->getHost());
    }

    public function testSchemeRejectsControlCharacters(): void
    {
        $this->assertThrows('Invalid URI scheme control characters.', static function (): void {
            new Uri('https://host.example')->withScheme("ht\ntp");
        });
    }

    public function testHostsRejectControlCharacters(): void
    {
        $this->assertThrows('Invalid URI host control characters.', static function (): void {
            new Uri('https://host.example')->withHost("exa\tmple.com");
        });
    }

    public function testDefaultPortsAreOmittedAndAnswerNull(): void
    {
        $http = new Uri('http://host.example:80/');
        $https = new Uri('https://host.example:443/');

        self::assertNull($http->getPort());
        self::assertSame('host.example', $http->getAuthority());
        self::assertSame('http://host.example/', (string) $http);
        self::assertNull($https->getPort());
        self::assertSame('host.example', $https->getAuthority());
    }

    public function testNonDefaultPortsSurvive(): void
    {
        $uri = new Uri('http://host.example:443/');

        self::assertSame(443, $uri->getPort());
        self::assertSame('host.example:443', $uri->getAuthority());
        self::assertSame('http://host.example:443/', (string) $uri);
    }

    public function testASchemeFlipReExposesADefaultPort(): void
    {
        $uri = new Uri('http://host.example:80/');

        $flipped = $uri->withScheme('https');

        self::assertSame(80, $flipped->getPort());
        self::assertSame('https://host.example:80/', (string) $flipped);
    }

    public function testWithPortAcceptsTheBoundariesAndClearsWithNull(): void
    {
        $uri = new Uri('http://host.example');

        self::assertSame(1, $uri->withPort(1)->getPort());
        self::assertSame(65535, $uri->withPort(65535)->getPort());
        self::assertNull($uri->withPort(null)->getPort());
    }

    public function testRejectsPortsOutsideTheRange(): void
    {
        $this->assertThrows('Invalid port: 0.', static function (): void {
            new Uri('http://host.example:0/');
        });
        $this->assertThrows('Invalid port: 65536.', static function (): void {
            new Uri('http://host.example')->withPort(65536);
        });
    }

    public function testParsesIpv6HostsWithPorts(): void
    {
        $uri = new Uri('https://[::1]:8443/path');

        self::assertSame('::1', $uri->getHost());
        self::assertSame(8443, $uri->getPort());
        self::assertSame('[::1]:8443', $uri->getAuthority());
        self::assertSame('https://[::1]:8443/path', (string) $uri);
    }

    public function testClearingTheHostClearsTheAuthority(): void
    {
        $uri = new Uri('https://user:pass@host.example:8080/path');

        self::assertSame('', $uri->withHost('')->getAuthority());
        self::assertSame('https:/path', (string) $uri->withHost(''));
    }

    public function testRejectsInvalidHosts(): void
    {
        $this->assertThrows('Invalid URI host.', static function (): void {
            new Uri('https://exa mple.com/');
        });
        $this->assertThrows('Invalid URI host.', static function (): void {
            new Uri('https://host.example')->withHost('-bad.example');
        });
        $this->assertThrows('Invalid URI host.', static function (): void {
            new Uri('https://host.example')->withHost('bad-.example');
        });
        $this->assertThrows('Invalid URI host.', static function (): void {
            new Uri('https://host.example')->withHost('a..b');
        });
        $this->assertThrows('Invalid URI host.', static function (): void {
            new Uri('https://host.example')->withHost('g:h');
        });
    }

    public function testRejectsOverlongHosts(): void
    {
        $this->assertThrows('Invalid URI host.', static function (): void {
            new Uri('https://host.example')->withHost(str_repeat('a', 254) . '.example');
        });
    }

    public function testHostsAreLowercasedTrimmedAndBracketStripped(): void
    {
        $uri = new Uri('https://host.example');

        self::assertSame('example_x.com', $uri->withHost(' EXAMPLE_x.com ')->getHost());
        self::assertSame('::1', $uri->withHost('[::1]')->getHost());
    }

    public function testUserInfoIsEncodedPerHalf(): void
    {
        $uri = new Uri('https://host.example')->withUserInfo('user name', 'p@ss:word');

        self::assertSame('user%20name:p%40ss%3Aword', $uri->getUserInfo());
        self::assertSame('user%20name:p%40ss%3Aword@host.example', $uri->getAuthority());
    }

    public function testUserInfoWithoutAPasswordHasNoSeparator(): void
    {
        $uri = new Uri('https://host.example')->withUserInfo('user');

        self::assertSame('user', $uri->getUserInfo());
        self::assertSame('user@host.example', $uri->getAuthority());
        self::assertSame('https://user@host.example', (string) $uri);
    }

    public function testUserInfoRejectsControlCharacters(): void
    {
        $this->assertThrows('Invalid URI user info control characters.', static function (): void {
            new Uri('https://host.example')->withUserInfo("us\ner");
        });
        $this->assertThrows('Invalid URI user info control characters.', static function (): void {
            new Uri('https://host.example')->withUserInfo('user', "pa\nss");
        });
    }

    public function testPercentEncodesDisallowedBytesInEveryComponent(): void
    {
        $uri = new Uri('https://host.example')->withPath('/a b')
            ->withQuery('a=b c')
            ->withFragment('f g')
        ;

        self::assertSame('/a%20b', $uri->getPath());
        self::assertSame('a=b%20c', $uri->getQuery());
        self::assertSame('f%20g', $uri->getFragment());
        self::assertSame('https://host.example/a%20b?a=b%20c#f%20g', (string) $uri);
    }

    public function testExistingPercentTriplesAreUppercasedAndPreserved(): void
    {
        $uri = new Uri('https://host.example')->withPath('/%2f%x');

        self::assertSame('/%2F%25x', $uri->getPath());
    }

    public function testPartialAndBrokenPercentTriplesAreEncoded(): void
    {
        $uri = new Uri('https://host.example');

        self::assertSame('%AB', $uri->withPath('%ab')->getPath());
        self::assertSame('%25a', $uri->withPath('%a')->getPath());
        self::assertSame('%25', $uri->withPath('%')->getPath());
        self::assertSame('%20af', $uri->withPath(' af')->getPath());
        self::assertSame('%25gf', $uri->withPath('%gf')->getPath());
    }

    public function testASchemeOnlyUriKeepsItsSchemeWhenRendering(): void
    {
        $uri = new Uri('mailto:someone@example.com');

        self::assertSame('mailto', $uri->getScheme());
        self::assertSame('someone@example.com', $uri->getPath());
        self::assertSame('mailto:someone@example.com', (string) $uri);
    }

    public function testMultibyteBytesAreEncodedIndividually(): void
    {
        $uri = new Uri('https://host.example')->withPath('/héllo');

        self::assertSame('/h%C3%A9llo', $uri->getPath());
    }

    public function testAllowedCharactersSurviveEncoding(): void
    {
        $path = ":/@!$&'()*+,;=-._~";

        $uri = new Uri('https://host.example')->withPath($path);

        self::assertSame($path, $uri->getPath());
    }

    public function testTheAuthorityPrefixesThePathWithASlash(): void
    {
        $uri = new Uri('https://host.example')->withPath('relative');

        self::assertSame('https://host.example/relative', (string) $uri);
    }

    public function testComponentsClearWithEmptyStrings(): void
    {
        $uri = new Uri('https://host.example/path?q#f')
            ->withQuery('')
            ->withFragment('')
        ;

        self::assertSame('https://host.example/path', (string) $uri);
    }

    public function testWithersAreImmutable(): void
    {
        $uri = new Uri('https://host.example/original');

        self::assertSame('http://host.example/original', (string) $uri->withScheme('http'));
        self::assertSame('https://host.example/original', (string) $uri);
        self::assertSame('https://user@host.example/original', (string) $uri->withUserInfo('user'));
        self::assertSame('https://host.example/original', (string) $uri);
        self::assertSame('https://other.example/original', (string) $uri->withHost('other.example'));
        self::assertSame('https://host.example/original', (string) $uri);
        self::assertSame('https://host.example:8080/original', (string) $uri->withPort(8080));
        self::assertSame('https://host.example/original', (string) $uri);
        self::assertSame('https://host.example/other', (string) $uri->withPath('/other'));
        self::assertSame('https://host.example/original', (string) $uri);
        self::assertSame('https://host.example/original?q=1', (string) $uri->withQuery('q=1'));
        self::assertSame('https://host.example/original', (string) $uri);
        self::assertSame('https://host.example/original#f', (string) $uri->withFragment('f'));
        self::assertSame('https://host.example/original', (string) $uri);
    }

    public function testRejectsControlCharactersInEveryComponent(): void
    {
        $uri = new Uri('https://host.example');

        $this->assertThrows('Invalid URI path control characters.', static function () use ($uri): void {
            $uri->withPath("/pa\nth");
        });
        $this->assertThrows('Invalid URI query control characters.', static function () use ($uri): void {
            $uri->withQuery("a=b\n");
        });
        $this->assertThrows('Invalid URI fragment control characters.', static function () use ($uri): void {
            $uri->withFragment("fr\ng");
        });
        $this->assertThrows('Invalid URI control characters.', static function (): void {
            new Uri("https://host.example/\n");
        });
    }

    /**
     * @param callable(): void $operation
     */
    private function assertThrows(string $message, callable $operation): void
    {
        try {
            $operation();
            self::fail('HttpException expected.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }
}
