<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\AuthorityPort;
use Zef\Framework\Http\BareAuthority;
use Zef\Framework\Http\BodyBudget;
use Zef\Framework\Http\BodyTransfer;
use Zef\Framework\Http\BracketedAuthority;
use Zef\Framework\Http\DnsHost;
use Zef\Framework\Http\FrontController;
use Zef\Framework\Http\HostAuthority;
use Zef\Framework\Http\HostComparison;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\LimitedInputStream;
use Zef\Framework\Http\NonOriginTarget;
use Zef\Framework\Http\RequestBodyPolicy;
use Zef\Framework\Http\RequestFactory;
use Zef\Framework\Http\ScriptEntry;
use Zef\Framework\Http\ServerHeaders;
use Zef\Framework\Http\ServerPath;
use Zef\Framework\Http\ServerPort;
use Zef\Framework\Http\ServerProtocol;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\ServerUploads;
use Zef\Framework\Http\ServerUri;
use Zef\Framework\Http\ServerValues;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\UploadCoercion;
use Zef\Framework\Http\UploadedFile;
use Zef\Framework\Http\UploadLeaf;
use Zef\Framework\Http\UploadNode;
use Zef\Framework\Http\UploadOrigin;

/**
 * @internal
 */
#[CoversClass(RequestFactory::class)]
#[CoversClass(AuthorityPort::class)]
#[CoversClass(BareAuthority::class)]
#[CoversClass(BodyBudget::class)]
#[CoversClass(BodyTransfer::class)]
#[CoversClass(BracketedAuthority::class)]
#[CoversClass(DnsHost::class)]
#[CoversClass(FrontController::class)]
#[CoversClass(HostAuthority::class)]
#[CoversClass(HostComparison::class)]
#[CoversClass(LimitedInputStream::class)]
#[CoversClass(NonOriginTarget::class)]
#[CoversClass(RequestBodyPolicy::class)]
#[CoversClass(ScriptEntry::class)]
#[CoversClass(ServerHeaders::class)]
#[CoversClass(ServerPort::class)]
#[CoversClass(ServerProtocol::class)]
#[CoversClass(ServerPath::class)]
#[CoversClass(ServerUploads::class)]
#[CoversClass(UploadOrigin::class)]
#[CoversClass(ServerUri::class)]
#[CoversClass(ServerValues::class)]
#[CoversClass(UploadCoercion::class)]
#[CoversClass(UploadLeaf::class)]
#[CoversClass(UploadNode::class)]
#[CoversClass(ServerRequest::class)]
final class RequestFactoryTest extends TestCase
{
    public function testFromServerBuildsTheRequestFromEverySource(): void
    {
        $server = $this->fpmServer();

        $request = RequestFactory::fromServer(
            $server,
            ['page' => '2'],
            ['session' => 'abc'],
            ['title' => 'x'],
            ['avatar' => $this->avatarSpec()],
        );

        self::assertSame('POST', $request->getMethod());
        self::assertSame('1.1', $request->getProtocolVersion());
        self::assertSame('/users', $request->getUri()->getPath());
        self::assertSame('example.com', $request->getUri()->getHost());
        self::assertSame('https', $request->getUri()->getScheme());
        self::assertSame(8443, $request->getUri()->getPort());
        self::assertSame('/users?q=1', $request->getRequestTarget());
        self::assertSame($server, $request->getServerParams());
        self::assertSame(['page' => '2'], $request->getQueryParams());
        self::assertSame(['session' => 'abc'], $request->getCookieParams());
        self::assertSame('text/html', $request->getHeaderLine('Accept'));
        self::assertSame('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        self::assertSame('12', $request->getHeaderLine('Content-Length'));
        self::assertSame('', $request->getBody()->getContents());
        $uploads = $request->getUploadedFiles();
        self::assertArrayHasKey('avatar', $uploads);
        self::assertInstanceOf(UploadedFile::class, $uploads['avatar']);
    }

    public function testTheMethodTokenIsNormalisedToUppercase(): void
    {
        $request = RequestFactory::fromServer(['REQUEST_METHOD' => 'get']);

        self::assertSame('GET', $request->getMethod());
    }

    public function testAMissingMethodDefaultsToGet(): void
    {
        $request = RequestFactory::fromServer([]);

        self::assertSame('GET', $request->getMethod());
    }

    public function testACorruptMethodEntryAnswersTheDefault(): void
    {
        $request = RequestFactory::fromServer(['REQUEST_METHOD' => 42]);

        self::assertSame('GET', $request->getMethod());
    }

    public function testACorruptProtocolEntryAnswersTheDefaultVersion(): void
    {
        $request = RequestFactory::fromServer(['SERVER_PROTOCOL' => 1.5]);

        self::assertSame('1.1', $request->getProtocolVersion());
    }

    public function testACorruptTargetEntryAnswersTheRootTarget(): void
    {
        $request = RequestFactory::fromServer(['REQUEST_URI' => ['/users']]);

        self::assertSame('/', $request->getRequestTarget());
        self::assertSame('/', $request->getUri()->getPath());
    }

    public function testTheBagsCarryNumericKeysVerbatim(): void
    {
        $request = RequestFactory::fromServer(
            [],
            [0 => 'a', 'page' => '2'],
            [1 => 'cookie', 'sid' => 'zzz'],
        );

        /** @var array<string, mixed> $expectedQuery the numeric key's folded view (the factory's docblock) */
        $expectedQuery = [0 => 'a', 'page' => '2'];

        /** @var array<string, mixed> $expectedCookies the numeric key's folded view (the factory's docblock) */
        $expectedCookies = [1 => 'cookie', 'sid' => 'zzz'];

        self::assertSame($expectedQuery, $request->getQueryParams());
        self::assertSame($expectedCookies, $request->getCookieParams());
    }

    /**
     * @dataProvider provideTheProtocolTokenNormalisesCases
     */
    #[DataProvider('provideTheProtocolTokenNormalisesCases')]
    public function testTheProtocolTokenNormalises(string $token, string $version): void
    {
        $request = RequestFactory::fromServer(['SERVER_PROTOCOL' => $token]);

        self::assertSame($version, $request->getProtocolVersion());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideTheProtocolTokenNormalisesCases(): iterable
    {
        yield 'dotted pair' => ['HTTP/1.0', '1.0'];

        yield 'single digit major two' => ['HTTP/2', '2'];

        yield 'single digit major three' => ['HTTP/3', '3'];

        yield 'two digit major degrades' => ['HTTP/22', '1.1'];

        yield 'suffix degrades' => ['HTTP/1.1+', '1.1'];

        yield 'garbage degrades' => ['ftp', '1.1'];

        yield 'prefixed dotted pair degrades' => ['xHTTP/2.0', '1.1'];

        yield 'suffixed dotted pair degrades' => ['HTTP/2.0y', '1.1'];

        yield 'prefixed single digit degrades' => ['xHTTP/2', '1.1'];

        yield 'empty degrades' => ['', '1.1'];
    }

    public function testNonStringServerValuesNeverBecomeHeaders(): void
    {
        $request = RequestFactory::fromServer([
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_X_COUNT' => 4,
            'REQUEST_TIME' => 1760000000,
        ]);

        self::assertSame('text/html', $request->getHeaderLine('Accept'));
        self::assertFalse($request->hasHeader('X-Count'));
    }

    public function testNonHeaderServerEntriesAreNotExtracted(): void
    {
        $request = RequestFactory::fromServer(['SCRIPT_NAME' => '/index.php']);

        self::assertSame(['Host' => ['localhost']], $request->getHeaders());
    }

    public function testServerNameFuelsTheAuthorityWhenHostHeaderIsAbsent(): void
    {
        $request = RequestFactory::fromServer(['SERVER_NAME' => 'api.zef.io']);

        self::assertSame('api.zef.io', $request->getUri()->getHost());
    }

    public function testAnEmptyHostHeaderFallsBackToTheServerName(): void
    {
        $request = RequestFactory::fromServer(['HTTP_HOST' => '', 'SERVER_NAME' => 'api.zef.io']);

        self::assertSame('api.zef.io', $request->getUri()->getHost());
    }

    public function testACorruptHostHeaderFallsBackToTheServerName(): void
    {
        $request = RequestFactory::fromServer(['HTTP_HOST' => 42, 'SERVER_NAME' => 'api.zef.io']);

        self::assertSame('api.zef.io', $request->getUri()->getHost());
    }

    /**
     * @dataProvider provideMalformedHostAuthoritiesFailClosedCases
     */
    #[DataProvider('provideMalformedHostAuthoritiesFailClosedCases')]
    public function testMalformedHostAuthoritiesFailClosed(string $host): void
    {
        $this->assertThrows(
            'Malformed Host header.',
            static function () use ($host): void {
                RequestFactory::fromServer([
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => $host,
                ]);
            },
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideMalformedHostAuthoritiesFailClosedCases(): iterable
    {
        yield 'userinfo delimiter' => ['exa@mple'];

        yield 'path delimiter' => ['exa/mple'];

        yield 'query delimiter' => ['exa?mple'];

        yield 'fragment delimiter' => ['exa#mple'];

        yield 'unclosed bracket' => ['[::1'];

        yield 'bracketed non-digit port' => ['[::1]:80x'];

        yield 'bracketed garbage tail' => ['[::1]x'];

        yield 'leading hyphen label' => ['-a'];

        yield 'trailing hyphen label' => ['a-'];

        yield 'empty label' => ['a..b'];

        yield 'double root dot' => ['a.b..'];

        yield 'lone dot pair' => ['..'];

        yield 'hyphen edge inside' => ['ab-'];

        yield 'invalid label character' => ['a!b'];

        yield 'empty port literal' => ['example.com:'];

        yield 'non-digit port' => ['example.com:8o'];

        yield 'zero port' => ['example.com:0'];

        yield 'bracketed zero port' => ['[::1]:0'];

        yield 'bracketed out of range port' => ['[::1]:65536'];

        yield 'out of range port' => ['example.com:65536'];

        yield 'empty host with port' => [':8080'];

        yield 'unbracketed IPv6 literal' => ['::1'];

        yield 'unbracketed mapped literal' => ['::ffff:10.0.0.1'];
    }

    public function testAnOverlongHostFailsClosed(): void
    {
        $this->assertThrows(
            'Malformed Host header.',
            static function (): void {
                RequestFactory::fromServer([
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => str_repeat('a.', 126) . 'aa',
                ]);
            },
        );
    }

    public function testTheFqdnRootDotIsStrippedFromTheHost(): void
    {
        $request = RequestFactory::fromServer([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'example.com.',
        ]);

        self::assertSame('example.com', $request->getUri()->getHost());
    }

    public function testUnderscoreIntranetNamesValidate(): void
    {
        $request = RequestFactory::fromServer([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'my_service.internal',
        ]);

        self::assertSame('my_service.internal', $request->getUri()->getHost());
    }

    public function testAMaximalLabelValidates(): void
    {
        $request = RequestFactory::fromServer([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => str_repeat('a', 63) . '.example',
        ]);

        self::assertSame(str_repeat('a', 63) . '.example', $request->getUri()->getHost());
    }

    public function testAnOverlongLabelFailsClosed(): void
    {
        $this->assertThrows(
            'Malformed Host header.',
            static function (): void {
                RequestFactory::fromServer([
                    'REQUEST_METHOD' => 'GET',
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => str_repeat('a', 64) . '.example',
                ]);
            },
        );
    }

    public function testAMaximalHostValidates(): void
    {
        $host = str_repeat('a', 63) . '.' . str_repeat('b', 63) . '.' . str_repeat('c', 63) . '.' . str_repeat('d', 61);

        $request = RequestFactory::fromServer([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => $host,
        ]);

        self::assertSame($host, $request->getUri()->getHost());
    }

    public function testAHostlessServerArrayAnswersLocalhost(): void
    {
        $request = RequestFactory::fromServer([]);

        self::assertSame('localhost', $request->getUri()->getHost());
    }

    /**
     * @dataProvider provideTheUriIsBuiltFromTheServerArrayCases
     *
     * @param array<string, mixed> $server
     */
    #[DataProvider('provideTheUriIsBuiltFromTheServerArrayCases')]
    public function testTheUriIsBuiltFromTheServerArray(array $server, string $expected): void
    {
        $request = RequestFactory::fromServer($server);

        self::assertSame($expected, (string) $request->getUri());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideTheUriIsBuiltFromTheServerArrayCases(): iterable
    {
        yield 'ipv6 literal with port' => [
            ['HTTP_HOST' => '[::1]:8080', 'REQUEST_URI' => '/'],
            'http://[::1]:8080/',
        ];

        yield 'ipv6 literal without port' => [
            ['HTTP_HOST' => '[::1]', 'REQUEST_URI' => '/'],
            'http://[::1]/',
        ];

        yield 'default http port omitted' => [
            ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => '80', 'REQUEST_URI' => '/'],
            'http://example.com/',
        ];

        yield 'default https port omitted' => [
            ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => '443', 'HTTPS' => 'on', 'REQUEST_URI' => '/'],
            'https://example.com/',
        ];

        yield 'non numeric port omitted' => [
            ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => 'port', 'REQUEST_URI' => '/'],
            'http://example.com/',
        ];

        yield 'zero port omitted' => [
            ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => '0', 'REQUEST_URI' => '/'],
            'http://example.com/',
        ];

        yield 'out of range port omitted' => [
            ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => '65536', 'REQUEST_URI' => '/'],
            'http://example.com/',
        ];

        yield 'empty target answers root' => [
            ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => ''],
            'http://example.com/',
        ];

        yield 'network path target is not a host' => [
            ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '//foo/bar'],
            'http://example.com//foo/bar',
        ];

        yield 'absolute form target collapses to its path' => [
            ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => 'http://other.example.com/x?q=2'],
            'http://example.com/x?q=2',
        ];

        yield 'asterisk form target collapses to root' => [
            ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '*'],
            'http://example.com/',
        ];

        yield 'fragment is stripped from the uri' => [
            ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/download#latest'],
            'http://example.com/download',
        ];

        yield 'front controller script is stripped' => [
            [
                'HTTP_HOST' => 'example.com',
                'REQUEST_URI' => '/index.php/users',
                'SCRIPT_NAME' => '/index.php',
                'SCRIPT_FILENAME' => '/var/www/index.php',
            ],
            'http://example.com/users',
        ];

        yield 'bare front controller answers root' => [
            [
                'HTTP_HOST' => 'example.com',
                'REQUEST_URI' => '/index.php',
                'SCRIPT_NAME' => '/index.php',
                'SCRIPT_FILENAME' => '/var/www/index.php',
            ],
            'http://example.com/',
        ];

        yield 'non front controller script is kept' => [
            [
                'HTTP_HOST' => 'example.com',
                'REQUEST_URI' => '/status',
                'SCRIPT_NAME' => '/index.php',
                'SCRIPT_FILENAME' => '/var/www/health.php',
            ],
            'http://example.com/status',
        ];

        yield 'off marker answers http' => [
            ['HTTP_HOST' => 'example.com', 'HTTPS' => 'off', 'REQUEST_URI' => '/'],
            'http://example.com/',
        ];

        yield 'falsy zero marker answers http' => [
            ['HTTP_HOST' => 'example.com', 'HTTPS' => '0', 'REQUEST_URI' => '/'],
            'http://example.com/',
        ];

        yield 'empty marker answers http' => [
            ['HTTP_HOST' => 'example.com', 'HTTPS' => '', 'REQUEST_URI' => '/'],
            'http://example.com/',
        ];

        yield 'corrupt marker answers http' => [
            ['HTTP_HOST' => 'example.com', 'HTTPS' => true, 'REQUEST_URI' => '/'],
            'http://example.com/',
        ];

        yield 'corrupt port entry is omitted' => [
            ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => 8080, 'REQUEST_URI' => '/'],
            'http://example.com/',
        ];

        yield 'explicit http port is kept' => [
            ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => '8080', 'REQUEST_URI' => '/'],
            'http://example.com:8080/',
        ];

        yield 'explicit https port is kept' => [
            ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => '8443', 'HTTPS' => 'on', 'REQUEST_URI' => '/'],
            'https://example.com:8443/',
        ];

        yield 'lowest port is kept' => [
            ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => '1', 'REQUEST_URI' => '/'],
            'http://example.com:1/',
        ];

        yield 'highest port is kept' => [
            ['HTTP_HOST' => 'example.com', 'SERVER_PORT' => '65535', 'REQUEST_URI' => '/'],
            'http://example.com:65535/',
        ];

        yield 'authority port wins over the server port' => [
            ['HTTP_HOST' => 'example.com:8443', 'SERVER_PORT' => '8080', 'REQUEST_URI' => '/'],
            'http://example.com:8443/',
        ];

        yield 'bracketed lowest port is kept' => [
            ['HTTP_HOST' => '[::1]:1', 'REQUEST_URI' => '/'],
            'http://[::1]:1/',
        ];

        yield 'bracketed highest port is kept' => [
            ['HTTP_HOST' => '[::1]:65535', 'REQUEST_URI' => '/'],
            'http://[::1]:65535/',
        ];

        yield 'multibyte path splits its query' => [
            ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/héllo?q=1'],
            'http://example.com/h%C3%A9llo?q=1',
        ];

        yield 'multibyte path strips its fragment' => [
            ['HTTP_HOST' => 'example.com', 'REQUEST_URI' => '/héllo#frag?q=1'],
            'http://example.com/h%C3%A9llo?q=1',
        ];

        yield 'multibyte script prefix strips' => [
            [
                'HTTP_HOST' => 'example.com',
                'REQUEST_URI' => '/éé/index.php/users',
                'SCRIPT_NAME' => '/éé/index.php',
                'SCRIPT_FILENAME' => '/var/www/index.php',
            ],
            'http://example.com/users',
        ];

        yield 'non front controller entry point is kept verbatim' => [
            [
                'HTTP_HOST' => 'example.com',
                'REQUEST_URI' => '/index.php',
                'SCRIPT_NAME' => '/index.php',
                'SCRIPT_FILENAME' => '/var/www/health.php',
            ],
            'http://example.com/index.php',
        ];

        yield 'prefix overlap is not stripped' => [
            [
                'HTTP_HOST' => 'example.com',
                'REQUEST_URI' => '/index.phpx',
                'SCRIPT_NAME' => '/index.php',
                'SCRIPT_FILENAME' => '/var/www/index.php',
            ],
            'http://example.com/index.phpx',
        ];

        yield 'kelvin sign host folds to ascii' => [
            ['HTTP_HOST' => "\u{212A}ELVIN.example", 'REQUEST_URI' => '/'],
            'http://kelvin.example/',
        ];
    }

    public function testTheRawTargetStaysVerbatimWhileTheUriPathIsNormalised(): void
    {
        $request = RequestFactory::fromServer([
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/index.php/users?q=1',
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => '/var/www/index.php',
        ]);

        self::assertSame('/index.php/users?q=1', $request->getRequestTarget());
        self::assertSame('/users', $request->getUri()->getPath());
        self::assertSame('q=1', $request->getUri()->getQuery());
    }

    public function testAMissingTargetDerivesFromTheUri(): void
    {
        $request = RequestFactory::fromServer([
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/docs?page=3',
        ]);

        self::assertSame('/docs?page=3', $request->getRequestTarget());
    }

    public function testATargetWithControlBytesFailsClosed(): void
    {
        $this->assertThrows(
            'Invalid URI control characters.',
            static function (): void {
                RequestFactory::fromServer(['REQUEST_URI' => "/users\r\nX-Injected: 1"]);
            },
        );
    }

    /**
     * @dataProvider provideAnAuthorityFormTargetFailsClosedCases
     */
    #[DataProvider('provideAnAuthorityFormTargetFailsClosedCases')]
    public function testAnAuthorityFormTargetFailsClosed(string $method, string $target): void
    {
        $this->assertThrows(
            'Malformed request target.',
            static function () use ($method, $target): void {
                RequestFactory::fromServer([
                    'REQUEST_METHOD' => $method,
                    'REQUEST_URI' => $target,
                    'HTTP_HOST' => 'example.com',
                ]);
            },
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideAnAuthorityFormTargetFailsClosedCases(): iterable
    {
        yield 'connect reg-name authority' => ['CONNECT', 'example.com:443'];

        yield 'connect bracketed authority' => ['CONNECT', '[::1]:443'];

        yield 'connect portless authority' => ['CONNECT', 'example.com'];

        yield 'authority on a plain method' => ['GET', 'example.com:443'];
    }

    public function testTheGlobalsBagDowngradesFilesTheEngineDisowns(): void
    {
        $server = $_SERVER;
        $get = $_GET;
        $cookie = $_COOKIE;
        $post = $_POST;
        $files = $_FILES;

        $_SERVER = ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'zef.io'];
        $_GET = [];
        $_COOKIE = [];
        $_POST = [];
        $_FILES = [
            'avatar' => [
                'name' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => __FILE__,
                'error' => \UPLOAD_ERR_OK,
                'size' => 2048,
            ],
            'photos' => [
                'name' => ['a.png', 'b.png'],
                'type' => ['image/png', 'image/png'],
                'tmp_name' => [__FILE__, ''],
                'error' => [\UPLOAD_ERR_OK, \UPLOAD_ERR_NO_FILE],
                'size' => [10, 20],
            ],
        ];

        try {
            $request = RequestFactory::fromGlobals();

            $uploads = $request->getUploadedFiles();
            $avatar = $uploads['avatar'];
            self::assertInstanceOf(UploadedFile::class, $avatar);
            $photos = $uploads['photos'];
            self::assertIsArray($photos);
            $firstPhoto = $photos[0];
            self::assertInstanceOf(UploadedFile::class, $firstPhoto);
            $secondPhoto = $photos[1];
            self::assertInstanceOf(UploadedFile::class, $secondPhoto);
            self::assertSame(\UPLOAD_ERR_NO_FILE, $avatar->getError(), 'a path the engine disowns is no payload');
            self::assertSame(\UPLOAD_ERR_NO_FILE, $firstPhoto->getError(), 'the nested disowned leaf is downgraded');
            self::assertSame(\UPLOAD_ERR_NO_FILE, $secondPhoto->getError(), 'the leaf that was already honest is untouched');

            $this->assertThrows(
                'Uploaded file is not available (error 4).',
                static function () use ($avatar): void {
                    $avatar->getStream();
                },
            );
        } finally {
            $_SERVER = $server;
            $_GET = $get;
            $_COOKIE = $cookie;
            $_POST = $post;
            $_FILES = $files;
        }
    }

    public function testTheExplicitSourceKeepsTheEngineDisownedFileTheCallerNamed(): void
    {
        $request = RequestFactory::fromServer([], null, null, null, [
            'avatar' => [
                'name' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => __FILE__,
                'error' => \UPLOAD_ERR_OK,
                'size' => 2048,
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        $avatar = $uploads['avatar'];
        self::assertInstanceOf(UploadedFile::class, $avatar);
        self::assertSame(\UPLOAD_ERR_OK, $avatar->getError(), 'the explicitly-sourced lane owns its own trust');
        self::assertSame('<?php', $avatar->getStream()->read(5));
    }

    /**
     * @dataProvider provideTheFormBodyIsHonouredOnlyForFormMediaTypesCases
     *
     * @param null|array<string, mixed> $expected
     */
    #[DataProvider('provideTheFormBodyIsHonouredOnlyForFormMediaTypesCases')]
    public function testTheFormBodyIsHonouredOnlyForFormMediaTypes(
        string $contentType,
        ?array $expected,
    ): void {
        $request = RequestFactory::fromServer(
            ['CONTENT_TYPE' => $contentType],
            null,
            null,
            ['title' => 'x'],
        );

        self::assertSame($expected, $request->getParsedBody());
    }

    /**
     * @return iterable<string, array{string, null|array<string, mixed>}>
     */
    public static function provideTheFormBodyIsHonouredOnlyForFormMediaTypesCases(): iterable
    {
        yield 'urlencoded honours the sapi body' => [
            'application/x-www-form-urlencoded',
            ['title' => 'x'],
        ];

        yield 'multipart honours the sapi body' => [
            'multipart/form-data; boundary=abc',
            ['title' => 'x'],
        ];

        yield 'json starts null' => ['application/json', null];

        yield 'no content type starts null' => ['', null];
    }

    public function testTheFormMediaTypeComparisonIsCaseInsensitive(): void
    {
        $request = RequestFactory::fromServer(
            ['CONTENT_TYPE' => 'Application/X-WWW-Form-URLEncoded'],
            null,
            null,
            ['title' => 'x'],
        );

        self::assertSame(['title' => 'x'], $request->getParsedBody());
    }

    public function testSurroundingWhitespaceAroundTheContentTypeIsTolerated(): void
    {
        $request = RequestFactory::fromServer(
            ['CONTENT_TYPE' => ' application/x-www-form-urlencoded '],
            null,
            null,
            ['title' => 'x'],
        );

        self::assertSame(['title' => 'x'], $request->getParsedBody());
    }

    public function testTheMethodTokenFoldsThroughTheFullUnicodeCaseMap(): void
    {
        $request = RequestFactory::fromServer(['REQUEST_METHOD' => "\u{017F}"]);

        // U+017F LATIN SMALL LETTER LONG S upper-cases to the ASCII "S"
        // under the Unicode case map; a byte-wise fold would leave the
        // raw bytes behind and the method grammar would reject them.
        self::assertSame('S', $request->getMethod());
    }

    public function testTheHeaderNameFoldsThroughTheFullUnicodeCaseMap(): void
    {
        $request = RequestFactory::fromServer(['HTTP_' . "\u{212A}EY" => 'v']);

        // U+212A KELVIN SIGN lower-cases to the ASCII "k" under the
        // Unicode case map; a byte-wise fold would leave the raw bytes
        // behind and the header-store grammar would reject them.
        self::assertSame('v', $request->getHeaderLine('key'));
    }

    public function testMultiWordHeaderNamesCarryDashes(): void
    {
        $request = RequestFactory::fromServer(['HTTP_X_REQUESTED_WITH' => 'xhr']);

        self::assertSame('xhr', $request->getHeaderLine('X-Requested-With'));
    }

    public function testAPseudoHttpPrefixedEntryIsNotAHeader(): void
    {
        $request = RequestFactory::fromServer([
            'XHTTP_ACCEPT' => 'text/html',
            'HTTP_HOST' => 'example.com',
        ]);

        self::assertFalse($request->hasHeader('accept'));
        self::assertSame('example.com', $request->getHeaderLine('Host'));
    }

    public function testAnHttpPrefixedKeyWithANewlineIsNotAHeader(): void
    {
        $request = RequestFactory::fromServer([
            'HTTP_A' . "\n" . 'B' => 'v',
            'HTTP_HOST' => 'example.com',
        ]);

        self::assertFalse($request->hasHeader('a'));
    }

    /**
     * @dataProvider provideTheExplicitPortFollowsItsValidationMatrixCases
     *
     * @param array<string, mixed> $server the SAPI server entries
     * @param 'http'|'https'       $scheme the effective scheme
     */
    #[DataProvider('provideTheExplicitPortFollowsItsValidationMatrixCases')]
    public function testTheExplicitPortFollowsItsValidationMatrix(array $server, string $scheme, ?int $expected): void
    {
        self::assertSame($expected, ServerPort::resolve($server, $scheme));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, 'http'|'https', null|int}>
     */
    public static function provideTheExplicitPortFollowsItsValidationMatrixCases(): iterable
    {
        yield 'default http port is omitted' => [['SERVER_PORT' => '80'], 'http', null];

        yield 'default https port is omitted' => [['SERVER_PORT' => '443'], 'https', null];

        yield 'explicit http port is kept' => [['SERVER_PORT' => '8080'], 'http', 8080];

        yield 'explicit https port is kept' => [['SERVER_PORT' => '8443'], 'https', 8443];

        yield 'lowest port is kept' => [['SERVER_PORT' => '1'], 'http', 1];

        yield 'highest port is kept' => [['SERVER_PORT' => '65535'], 'http', 65535];

        yield 'zero port is omitted' => [['SERVER_PORT' => '0'], 'http', null];

        yield 'out of range port is omitted' => [['SERVER_PORT' => '65536'], 'http', null];

        yield 'non numeric port is omitted' => [['SERVER_PORT' => 'port'], 'http', null];

        yield 'trailing garbage port is omitted' => [['SERVER_PORT' => '8080x'], 'http', null];

        yield 'absent port is omitted' => [[], 'http', null];
    }

    public function testTheScriptEntryIsAFrontControllerOnlyWhenTheBasenamesMatch(): void
    {
        self::assertTrue(ScriptEntry::isFrontController([
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => '/var/www/index.php',
        ]));
        self::assertFalse(ScriptEntry::isFrontController([
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => '/var/www/health.php',
        ]));
        self::assertFalse(ScriptEntry::isFrontController([
            'SCRIPT_NAME' => '/index.php',
        ]));
        self::assertFalse(ScriptEntry::isFrontController([
            'SCRIPT_NAME' => '/',
        ]));
        self::assertFalse(ScriptEntry::isFrontController([]));
    }

    public function testTheScriptEntryPathAnswersTheParsedScriptName(): void
    {
        self::assertSame('/index.php', ScriptEntry::path(['SCRIPT_NAME' => '/index.php']));
        self::assertSame('', ScriptEntry::path([]));
    }

    public function testOmittedBagsDefaultToEmpty(): void
    {
        $request = RequestFactory::fromServer([]);

        self::assertSame([], $request->getServerParams());
        self::assertSame([], $request->getQueryParams());
        self::assertSame([], $request->getCookieParams());
        self::assertSame([], $request->getUploadedFiles());
        self::assertNull($request->getParsedBody());
    }

    public function testASingleUploadSpecBecomesOneLeaf(): void
    {
        $request = RequestFactory::fromServer([], null, null, null, [
            'avatar' => [
                'name' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => __FILE__,
                'error' => \UPLOAD_ERR_OK,
                'size' => 2048,
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        self::assertInstanceOf(UploadedFile::class, $uploads['avatar']);
        self::assertSame('avatar.png', $uploads['avatar']->getClientFilename());
        self::assertSame('image/png', $uploads['avatar']->getClientMediaType());
        self::assertSame(2048, $uploads['avatar']->getSize());
        self::assertSame(\UPLOAD_ERR_OK, $uploads['avatar']->getError());
        self::assertSame('<?php', $uploads['avatar']->getStream()->read(5));
    }

    public function testAMultiFileFieldBecomesAnIndexedList(): void
    {
        $request = RequestFactory::fromServer([], null, null, null, [
            'photos' => [
                'name' => ['a.png', 'b.png'],
                'type' => ['image/png', 'image/png'],
                'tmp_name' => [__FILE__, __FILE__],
                'error' => [\UPLOAD_ERR_OK, \UPLOAD_ERR_OK],
                'size' => [10, 20],
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        self::assertIsArray($uploads['photos']);
        self::assertCount(2, $uploads['photos']);
        self::assertInstanceOf(UploadedFile::class, $uploads['photos'][0]);
        self::assertInstanceOf(UploadedFile::class, $uploads['photos'][1]);
        self::assertSame('a.png', $uploads['photos'][0]->getClientFilename());
        self::assertSame('b.png', $uploads['photos'][1]->getClientFilename());
    }

    public function testANestedUploadSpecWalksTheErrorAxis(): void
    {
        $request = RequestFactory::fromServer([], null, null, null, [
            'gallery' => [
                'name' => ['cover' => ['c.png']],
                'type' => ['cover' => ['image/png']],
                'tmp_name' => ['cover' => [__FILE__]],
                'error' => ['cover' => [\UPLOAD_ERR_OK]],
                'size' => ['cover' => [7]],
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        $gallery = $uploads['gallery'];
        self::assertIsArray($gallery);
        $cover = $gallery['cover'];
        self::assertIsArray($cover);
        self::assertInstanceOf(UploadedFile::class, $cover[0]);
        self::assertSame('c.png', $cover[0]->getClientFilename());
    }

    public function testADigitStringSizeCoerces(): void
    {
        $request = RequestFactory::fromServer([], null, null, null, [
            'avatar' => [
                'name' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => '',
                'error' => \UPLOAD_ERR_NO_FILE,
                'size' => '2048',
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        self::assertInstanceOf(UploadedFile::class, $uploads['avatar']);
        self::assertSame(2048, $uploads['avatar']->getSize());
    }

    public function testAFloatSizeAnswersUnknown(): void
    {
        $request = RequestFactory::fromServer([], null, null, null, [
            'avatar' => [
                'name' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => '',
                'error' => \UPLOAD_ERR_NO_FILE,
                'size' => 3.5,
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        self::assertInstanceOf(UploadedFile::class, $uploads['avatar']);

        // An unknown size (null) falls back to the empty stream's own
        // size, which is zero -- never the truncated cast of the raw
        // float.
        self::assertSame(0, $uploads['avatar']->getSize());
    }

    public function testTwoUploadFieldsAreBothNormalised(): void
    {
        $request = RequestFactory::fromServer([], null, null, null, [
            'avatar' => [
                'name' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => '',
                'error' => \UPLOAD_ERR_NO_FILE,
                'size' => 0,
            ],
            'logo' => [
                'name' => 'logo.png',
                'type' => 'image/png',
                'tmp_name' => '',
                'error' => \UPLOAD_ERR_NO_FILE,
                'size' => 0,
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        self::assertInstanceOf(UploadedFile::class, $uploads['avatar']);
        self::assertInstanceOf(UploadedFile::class, $uploads['logo']);
    }

    public function testAPreNormalisedLeafPassesThroughAndLaterFieldsStillWalk(): void
    {
        $uploadedFile = new UploadedFile(Stream::fromString(''), null, \UPLOAD_ERR_NO_FILE);

        $request = RequestFactory::fromServer([], null, null, null, [
            'direct' => $uploadedFile,
            'avatar' => [
                'name' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => '',
                'error' => \UPLOAD_ERR_NO_FILE,
                'size' => 0,
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        self::assertSame($uploadedFile, $uploads['direct']);
        self::assertInstanceOf(UploadedFile::class, $uploads['avatar']);
    }

    public function testAGhostUploadIsDowngradedToNoFile(): void
    {
        $request = RequestFactory::fromServer([], null, null, null, [
            'avatar' => [
                'name' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => '',
                'error' => \UPLOAD_ERR_OK,
                'size' => 2048,
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        self::assertInstanceOf(UploadedFile::class, $uploads['avatar']);
        self::assertSame(\UPLOAD_ERR_NO_FILE, $uploads['avatar']->getError());
    }

    public function testADigitStringErrorCodeCoerces(): void
    {
        $request = RequestFactory::fromServer([], null, null, null, [
            'avatar' => [
                'name' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => '',
                'error' => (string) \UPLOAD_ERR_CANT_WRITE,
                'size' => 0,
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        self::assertInstanceOf(UploadedFile::class, $uploads['avatar']);
        self::assertSame(\UPLOAD_ERR_CANT_WRITE, $uploads['avatar']->getError());
    }

    public function testAnUnrecognisableErrorCodeWithPayloadStaysNoFile(): void
    {
        $request = RequestFactory::fromServer([], null, null, null, [
            'avatar' => [
                'name' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => __FILE__,
                'error' => 'boom',
                'size' => 5,
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        self::assertInstanceOf(UploadedFile::class, $uploads['avatar']);
        self::assertSame(\UPLOAD_ERR_NO_FILE, $uploads['avatar']->getError());
    }

    public function testAnUnrecognisableErrorCodeAnswersNoFile(): void
    {
        $request = RequestFactory::fromServer([], null, null, null, [
            'avatar' => [
                'name' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => '',
                'error' => 'boom',
                'size' => 0,
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        self::assertInstanceOf(UploadedFile::class, $uploads['avatar']);
        self::assertSame(\UPLOAD_ERR_NO_FILE, $uploads['avatar']->getError());
    }

    public function testASpecWithoutAnErrorAxisFailsClosedThroughTheTreeGuard(): void
    {
        $this->assertThrows(
            'Uploaded files must contain only UploadedFileInterface leaves.',
            static function (): void {
                RequestFactory::fromServer([], null, null, null, [
                    'not-an-upload' => ['value' => 42],
                ]);
            },
        );
    }

    public function testNonStringClientMetadataAnswersNull(): void
    {
        $request = RequestFactory::fromServer([], null, null, null, [
            'avatar' => [
                'name' => 42,
                'type' => ['not', 'a', 'string'],
                'tmp_name' => '',
                'error' => \UPLOAD_ERR_NO_FILE,
                'size' => null,
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        self::assertInstanceOf(UploadedFile::class, $uploads['avatar']);
        self::assertNull($uploads['avatar']->getClientFilename());
        self::assertNull($uploads['avatar']->getClientMediaType());
    }

    public function testAFailedUploadRefusesItsStream(): void
    {
        $request = RequestFactory::fromServer([], null, null, null, [
            'avatar' => [
                'name' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => '/nonexistent',
                'error' => \UPLOAD_ERR_CANT_WRITE,
                'size' => 5,
            ],
        ]);

        $uploads = $request->getUploadedFiles();
        self::assertInstanceOf(UploadedFile::class, $uploads['avatar']);
        $avatar = $uploads['avatar'];

        $this->assertThrows(
            'Uploaded file is not available (error 7).',
            static function () use ($avatar): void {
                $avatar->getStream();
            },
        );
    }

    public function testAServerClaimAboveTheDefaultPolicyFailsFast(): void
    {
        $this->assertThrows(
            'Request body exceeds configured size limit.',
            static function (): void {
                RequestFactory::fromServer([
                    'REQUEST_METHOD' => 'POST',
                    'REQUEST_URI' => '/',
                    'HTTP_HOST' => 'zef.io',
                    'CONTENT_LENGTH' => '2097153',
                ]);
            },
        );
    }

    /**
     * The claim that lands exactly on the default limit is compliant: the
     * guard is a strict comparison, never an off-by-one.
     */
    public function testAServerClaimAtExactlyTheDefaultPolicyIsAccepted(): void
    {
        $request = RequestFactory::fromServer([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'zef.io',
            'CONTENT_LENGTH' => '2097152',
        ]);

        self::assertInstanceOf(LimitedInputStream::class, $request->getBody());
    }

    public function testAServerClaimAboveACustomPolicyFailsFast(): void
    {
        $this->assertThrows(
            'Request body exceeds configured size limit.',
            static function (): void {
                RequestFactory::fromServer(
                    [
                        'REQUEST_METHOD' => 'POST',
                        'REQUEST_URI' => '/',
                        'HTTP_HOST' => 'zef.io',
                        'CONTENT_LENGTH' => '101',
                    ],
                    null,
                    null,
                    null,
                    null,
                    new RequestBodyPolicy(100),
                );
            },
        );
    }

    /**
     * A field value may legally carry surrounding OWS; the claim is judged
     * after the whitespace is trimmed away, so padded oversize claims fail
     * just like bare ones.
     */
    public function testAServerClaimCarryingSurroundingWhitespaceIsTrimmed(): void
    {
        $this->assertThrows(
            'Request body exceeds configured size limit.',
            static function (): void {
                RequestFactory::fromServer(
                    [
                        'REQUEST_METHOD' => 'POST',
                        'REQUEST_URI' => '/',
                        'HTTP_HOST' => 'zef.io',
                        'CONTENT_LENGTH' => ' 101 ',
                    ],
                    null,
                    null,
                    null,
                    null,
                    new RequestBodyPolicy(100),
                );
            },
        );
    }

    /**
     * A malformed claim is never a size signal by itself: it is left to the
     * lazy half's byte counting over the delivered stream.
     */
    public function testAMalformedClaimIsLeftToTheLazyHalf(): void
    {
        $request = RequestFactory::fromServer(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/',
                'HTTP_HOST' => 'zef.io',
                'CONTENT_LENGTH' => '101abc',
            ],
            null,
            null,
            null,
            null,
            new RequestBodyPolicy(100),
        );

        self::assertInstanceOf(LimitedInputStream::class, $request->getBody());
    }

    /**
     * A corrupt (non-string) claim entry is absence, like any other server
     * value -- the extraction's own reading policy, not a size signal.
     */
    public function testACorruptClaimEntryCarriesNoSizeSignal(): void
    {
        $request = RequestFactory::fromServer([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'zef.io',
            'CONTENT_LENGTH' => 3145728,
        ]);

        self::assertInstanceOf(LimitedInputStream::class, $request->getBody());
    }

    /**
     * Secure by default: even the policy-less caller gets a bounded body,
     * and the CLI's empty `php://input` drains to '' under the wrapper.
     */
    public function testTheBodyIsServedUnderTheIngressPolicy(): void
    {
        $request = RequestFactory::fromServer([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'zef.io',
        ]);

        $body = $request->getBody();

        self::assertInstanceOf(LimitedInputStream::class, $body);
        self::assertFalse($body->isWritable());
        self::assertSame('', $body->getContents());
    }

    /**
     * The superglobal adapter threads the caller's policy through: an
     * oversize claim against the custom limit fails before the body is
     * even opened.
     */
    public function testFromGlobalsThreadsThePolicyThrough(): void
    {
        $server = $_SERVER;
        $get = $_GET;
        $cookie = $_COOKIE;
        $post = $_POST;
        $files = $_FILES;

        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'zef.io',
            'CONTENT_LENGTH' => '5',
        ];
        $_GET = [];
        $_COOKIE = [];
        $_POST = [];
        $_FILES = [];

        try {
            $this->assertThrows(
                'Request body exceeds configured size limit.',
                static function (): void {
                    RequestFactory::fromGlobals(new RequestBodyPolicy(4));
                },
            );
        } finally {
            $_SERVER = $server;
            $_GET = $get;
            $_COOKIE = $cookie;
            $_POST = $post;
            $_FILES = $files;
        }
    }

    public function testFromGlobalsReadsTheSuperglobals(): void
    {
        $server = $_SERVER;
        $get = $_GET;
        $cookie = $_COOKIE;
        $post = $_POST;
        $files = $_FILES;

        $_SERVER = [
            'REQUEST_METHOD' => 'PUT',
            'REQUEST_URI' => '/articles/12',
            'HTTP_HOST' => 'zef.io',
        ];
        $_GET = ['beta' => 'on'];
        $_COOKIE = ['sid' => 'zzz'];
        $_POST = ['title' => 'hello'];
        $_FILES = [];

        try {
            $request = RequestFactory::fromGlobals();

            self::assertSame('PUT', $request->getMethod());
            self::assertSame('/articles/12', $request->getUri()->getPath());
            self::assertSame('zef.io', $request->getUri()->getHost());
            self::assertSame(['beta' => 'on'], $request->getQueryParams());
            self::assertSame(['sid' => 'zzz'], $request->getCookieParams());
            self::assertNull($request->getParsedBody());
        } finally {
            $_SERVER = $server;
            $_GET = $get;
            $_COOKIE = $cookie;
            $_POST = $post;
            $_FILES = $files;
        }
    }

    public function testFromGlobalsTreatsAnEmptyPostBagAsNoFormBody(): void
    {
        $server = $_SERVER;
        $get = $_GET;
        $cookie = $_COOKIE;
        $post = $_POST;
        $files = $_FILES;

        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'zef.io',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ];
        $_GET = [];
        $_COOKIE = [];
        $_POST = [];
        $_FILES = [];

        try {
            $request = RequestFactory::fromGlobals();

            self::assertNull($request->getParsedBody());
        } finally {
            $_SERVER = $server;
            $_GET = $get;
            $_COOKIE = $cookie;
            $_POST = $post;
            $_FILES = $files;
        }
    }

    public function testFromGlobalsCarriesNumericQueryKeysVerbatim(): void
    {
        $server = $_SERVER;
        $get = $_GET;
        $cookie = $_COOKIE;
        $post = $_POST;
        $files = $_FILES;

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/?0=a&page=2',
            'HTTP_HOST' => 'zef.io',
        ];
        $_GET = [0 => 'a', 'page' => '2'];
        $_COOKIE = [];
        $_POST = [];
        $_FILES = [];

        try {
            $request = RequestFactory::fromGlobals();

            self::assertSame([0 => 'a', 'page' => '2'], $request->getQueryParams());
        } finally {
            $_SERVER = $server;
            $_GET = $get;
            $_COOKIE = $cookie;
            $_POST = $post;
            $_FILES = $files;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fpmServer(): array
    {
        return [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/users?q=1',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com:8443',
            'HTTPS' => 'on',
            'SCRIPT_NAME' => '/index.php',
            'SCRIPT_FILENAME' => '/var/www/index.php',
            'HTTP_ACCEPT' => 'text/html',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'CONTENT_LENGTH' => '12',
            'REQUEST_TIME' => 1760000000,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function avatarSpec(): array
    {
        return [
            'name' => 'avatar.png',
            'type' => 'image/png',
            'tmp_name' => '',
            'error' => \UPLOAD_ERR_NO_FILE,
            'size' => 0,
        ];
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
