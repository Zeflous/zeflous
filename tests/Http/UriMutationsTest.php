<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Uri;

/**
 * The PSR-7 URI's mutation protocol, covered as its own unit: every `with*`
 * answers a NEW value object with one component replaced (the copy-on-write
 * sharing contract), the guards stay in the grammar collaborators, and the
 * encodings are what the wire would actually carry.
 *
 * The trait is covered through its only consumer: PHPUnit 13 refuses a trait
 * as a coverage target, while a class's coverage includes the traits it
 * composes -- the same attribution UriTest already rides.
 *
 * @internal
 */
#[CoversClass(Uri::class)]
final class UriMutationsTest extends TestCase
{
    public function testWithSchemeReplacesTheComponentOnANewInstance(): void
    {
        $uri = new Uri('https://user:pass@host.example:8443/path;x?a=1&b=2#frag');

        $mutated = $uri->withScheme('HTTP');

        self::assertNotSame($uri, $mutated);
        self::assertSame('http', $mutated->getScheme());
        self::assertSame('https', $uri->getScheme());
    }

    public function testWithSchemeRefusesAnInvalidScheme(): void
    {
        $uri = new Uri('https://host.example');

        $this->expectException(HttpException::class);

        $uri->withScheme('1http');
    }

    public function testWithSchemeRefusesControlCharacters(): void
    {
        $uri = new Uri('https://host.example');

        $this->expectException(HttpException::class);

        $uri->withScheme("ht\0tp");
    }

    public function testWithUserInfoEncodesBothHalvesOnANewInstance(): void
    {
        $uri = new Uri('https://user:pass@host.example');

        $mutated = $uri->withUserInfo('new user', 'new pass');

        self::assertNotSame($uri, $mutated);
        self::assertSame('new%20user:new%20pass', $mutated->getUserInfo());
        self::assertSame('user:pass', $uri->getUserInfo());
    }

    public function testWithUserInfoWithoutAPasswordCarriesNoSeparator(): void
    {
        $uri = new Uri('https://user:pass@host.example');

        $mutated = $uri->withUserInfo('solo');

        self::assertSame('solo', $mutated->getUserInfo());
    }

    public function testWithUserInfoRefusesControlCharacters(): void
    {
        $uri = new Uri('https://host.example');

        $this->expectException(HttpException::class);

        $uri->withUserInfo("us\0er", 'pass');
    }

    public function testWithHostReplacesTheComponentOnANewInstance(): void
    {
        $uri = new Uri('https://user@host.example:8443/path');

        $mutated = $uri->withHost('other.example');

        self::assertNotSame($uri, $mutated);
        self::assertSame('other.example', $mutated->getHost());
        self::assertSame('host.example', $uri->getHost());
        self::assertSame('user@other.example:8443', $mutated->getAuthority());
    }

    public function testWithPortAcceptsTheNullRemovalAndBothBoundaries(): void
    {
        $uri = new Uri('https://host.example:8443/path');

        $mutated = $uri->withPort(null);

        self::assertNotSame($uri, $mutated);
        self::assertNull($mutated->getPort());
        self::assertSame(8443, $uri->getPort());

        $lowest = $uri->withPort(1);
        $highest = $uri->withPort(65535);

        self::assertSame(1, $lowest->getPort());
        self::assertSame(65535, $highest->getPort());
    }

    #[DataProvider('provideWithPortRefusesTheOutOfBoundsArmCases')]
    public function testWithPortRefusesTheOutOfBoundsArm(int $port): void
    {
        // A provider row per arm rather than a counted loop: the analyser
        // folds a loop's counter into the very number under test, while each
        // row carries its own refusal expectation.
        $this->expectException(HttpException::class);

        new Uri('https://host.example')->withPort($port);
    }

    /**
     * @return Iterator<string, array{int}> of the dataset name => the out-of-bounds port
     */
    public static function provideWithPortRefusesTheOutOfBoundsArmCases(): iterable
    {
        yield 'zero' => [0];

        yield 'above the range' => [65536];

        yield 'below the range' => [-1];
    }

    public function testWithPathEncodesOnANewInstance(): void
    {
        $uri = new Uri('https://host.example/path');

        $mutated = $uri->withPath('/a b/c');

        self::assertNotSame($uri, $mutated);
        self::assertSame('/a%20b/c', $mutated->getPath());
        self::assertSame('/path', $uri->getPath());
    }

    public function testWithPathRefusesControlCharacters(): void
    {
        $uri = new Uri('https://host.example/path');

        $this->expectException(HttpException::class);

        $uri->withPath("/pa\0th");
    }

    public function testWithQueryEncodesOnANewInstance(): void
    {
        $uri = new Uri('https://host.example/path?a=1&b=2');

        $mutated = $uri->withQuery('a b&c=d');

        self::assertNotSame($uri, $mutated);
        self::assertSame('a%20b&c=d', $mutated->getQuery());
        self::assertSame('a=1&b=2', $uri->getQuery());
    }

    public function testWithQueryRefusesControlCharacters(): void
    {
        $uri = new Uri('https://host.example');

        $this->expectException(HttpException::class);

        $uri->withQuery("a=1&b=\0");
    }

    public function testWithFragmentEncodesOnANewInstance(): void
    {
        $uri = new Uri('https://host.example/path#frag');

        $mutated = $uri->withFragment('fr ag');

        self::assertNotSame($uri, $mutated);
        self::assertSame('fr%20ag', $mutated->getFragment());
        self::assertSame('frag', $uri->getFragment());
    }

    public function testWithFragmentRefusesControlCharacters(): void
    {
        $uri = new Uri('https://host.example');

        $this->expectException(HttpException::class);

        $uri->withFragment("fr\0ag");
    }
}
