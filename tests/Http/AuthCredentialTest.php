<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\AuthCredential;
use Zef\Framework\Http\HttpException;

/**
 * The bearer credential's extraction grammar, on its own terms: the
 * RFC 6750 line's every edge -- the scheme's case, the SP-only
 * separator, the token68 vocabulary, the padding's trailing-only
 * placement, the one ceiling held on both sides of the seam, and the
 * OWS fold -- plus the anonymous handle's own identity and the
 * hand-construction bounds.
 *
 * @internal
 */
#[CoversClass(AuthCredential::class)]
final class AuthCredentialTest extends TestCase
{
    private const string TOKEN = 'Z2V0LWEtY3JlZGVudGlhbC1mcm9tLXRoZS10YWJsZQ';

    /**
     * @dataProvider provideTheGrammarAdmitsItsOwnVocabularyCases
     */
    #[DataProvider('provideTheGrammarAdmitsItsOwnVocabularyCases')]
    public function testTheGrammarAdmitsItsOwnVocabulary(string $line, string $token): void
    {
        $credential = AuthCredential::fromBearerLine($line);

        self::assertNotNull($credential, 'the line is a bearer credential');
        self::assertSame($token, $credential->handleId);
        self::assertSame('bearer', $credential->scope);
        self::assertSame(\PHP_INT_MAX, $credential->expiresAtMs);
    }

    /**
     * @return iterable<non-empty-string, array{string, string}>
     */
    public static function provideTheGrammarAdmitsItsOwnVocabularyCases(): iterable
    {
        $token = self::TOKEN;

        yield 'the canonical line' => ["Bearer {$token}", $token];

        yield 'lowercase scheme' => ["bearer {$token}", $token];

        yield 'mixed-case scheme' => ["BeArEr {$token}", $token];

        yield 'several separator spaces' => ["Bearer   {$token}", $token];

        yield 'surrounding OWS' => ["  Bearer {$token}  ", $token];

        yield 'trailing padding' => ["Bearer {$token}==", "{$token}=="];

        yield 'the token68 vocabulary' => ['Bearer a-b.c_d~e+f/g-h', 'a-b.c_d~e+f/g-h'];
    }

    /**
     * @dataProvider provideTheGrammarRefusesEverythingElseCases
     */
    #[DataProvider('provideTheGrammarRefusesEverythingElseCases')]
    public function testTheGrammarRefusesEverythingElse(?string $line): void
    {
        self::assertNull(
            AuthCredential::fromBearerLine((string) $line),
            'the line carries no bearer credential',
        );
    }

    /**
     * The refusals: the absent line, the other schemes, the separator
     * that is not SP, the tokens that are not token68, the
     * comma-joined second challenge a multi-header line becomes, and
     * the non-ASCII bytes the vocabulary does not carry.
     *
     * @return iterable<non-empty-string, array{null|string}>
     */
    public static function provideTheGrammarRefusesEverythingElseCases(): iterable
    {
        $token = self::TOKEN;

        yield 'absent line' => [null];

        yield 'empty line' => [''];

        yield 'scheme alone' => ['Bearer'];

        yield 'one separator space, no token' => ['Bearer '];

        yield 'separator spaces only' => ['Bearer   '];

        yield 'tab is not a separator' => ["Bearer\t{$token}"];

        yield 'another scheme' => ['Basic dXNlcjpwYXNz'];

        yield 'negotiate scheme' => ['Negotiate'];

        yield 'token with an inner space' => ['Bearer abc def'];

        yield 'padding in the middle' => ['Bearer ab=cd'];

        yield 'padding alone' => ['Bearer ==='];

        yield 'comma-joined second challenge' => ["Bearer {$token}, Bearer {$token}"];

        yield 'quoted-string token' => ['Bearer "a-token"'];

        yield 'non-ASCII bytes' => ['Bearer tökén-mit-ümläuten'];

        yield 'control byte' => ["Bearer a\x01b"];
    }

    public function testTheCeilingHoldsOnBothSidesOfTheSeam(): void
    {
        $exactly = $this->tokenOfLength(512);
        $over = $this->tokenOfLength(513);

        self::assertNotNull(AuthCredential::fromBearerLine("Bearer {$exactly}"), '512 is the ceiling, not the wall');
        self::assertNull(
            AuthCredential::fromBearerLine("Bearer {$over}"),
            'a token the handle cannot carry is not a credential',
        );
    }

    public function testTheAnonymousHandleIsItsOwnIdentity(): void
    {
        $anonymous = AuthCredential::anonymous();

        self::assertSame('anonymous', $anonymous->handleId);
        self::assertSame('public', $anonymous->scope);
        self::assertSame(0, $anonymous->expiresAtMs);
        self::assertTrue($anonymous->isAnonymous(), 'the pair the middleware constructs is the anonymous handle');
    }

    public function testAPresentedBearerAnonymousIsNotTheAnonymousHandle(): void
    {
        $presented = AuthCredential::fromBearerLine('Bearer ' . str_repeat('anonymous', 6));

        self::assertNotNull($presented);
        self::assertFalse(
            $presented->isAnonymous(),
            'a presented line claims the bearer scope, never the anonymous pair',
        );
    }

    public function testTheAnonymousPairNeedsBothWords(): void
    {
        $handleOnly = new AuthCredential('anonymous', 'bearer', 0);
        $scopeOnly = new AuthCredential('other-handle', 'public', 0);

        self::assertFalse($handleOnly->isAnonymous(), 'the handle alone is not the pair');
        self::assertFalse($scopeOnly->isAnonymous(), 'the scope alone is not the pair');
    }

    public function testAnEmptyHandleIdIsRefused(): void
    {
        try {
            new AuthCredential('', 'bearer', 0);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth credential handleId exceeds its bound.', $httpException->getMessage());
        }
    }

    public function testAMultiByteHandleIdIsMeasuredInCharacters(): void
    {
        $authCredential = new AuthCredential(str_repeat('é', 257), 'bearer', 0);

        self::assertSame(
            str_repeat('é', 257),
            $authCredential->handleId,
            '257 characters are 514 bytes, and the ceiling is 512 characters',
        );
    }

    public function testAnOverLongHandleIdIsRefused(): void
    {
        try {
            new AuthCredential($this->tokenOfLength(513), 'bearer', 0);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth credential handleId exceeds its bound.', $httpException->getMessage());
        }
    }

    public function testAnEmptyScopeIsRefused(): void
    {
        try {
            new AuthCredential(self::TOKEN, '', 0);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth credential scope exceeds its bound.', $httpException->getMessage());
        }
    }

    public function testAnOverLongScopeIsRefused(): void
    {
        try {
            new AuthCredential(self::TOKEN, str_repeat('s', 129), 0);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth credential scope exceeds its bound.', $httpException->getMessage());
        }
    }

    public function testANegativeExpiryIsRefused(): void
    {
        try {
            new AuthCredential(self::TOKEN, 'bearer', -1);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth credential expiry must be 0 or above.', $httpException->getMessage());
        }
    }

    /**
     * @dataProvider provideTheTokenShapeCensusCases
     */
    #[DataProvider('provideTheTokenShapeCensusCases')]
    public function testTheTokenShapeCensus(string $value, bool $expected): void
    {
        self::assertSame($expected, AuthCredential::isBearerToken($value));
    }

    /**
     * The shape census the static table's bounds read: the vocabulary
     * admits its own bytes and refuses the rest.
     *
     * @return iterable<non-empty-string, array{string, bool}>
     */
    public static function provideTheTokenShapeCensusCases(): iterable
    {
        yield 'the vocabulary' => ['a-b.c_d~e+f/g-h0123456789', true];

        yield 'trailing padding' => ['abc==', true];

        yield 'empty' => ['', false];

        yield 'padding alone' => ['==', false];

        yield 'inner space' => ['a b', false];

        yield 'comma' => ['a,b', false];

        yield 'padding in the middle' => ['a=b', false];
    }

    /**
     * One grammar-valid token of the given length.
     */
    private function tokenOfLength(int $length): string
    {
        return str_repeat('a', $length - 2) . '==';
    }
}
