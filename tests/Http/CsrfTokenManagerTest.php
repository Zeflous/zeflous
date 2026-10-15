<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\CsrfTokenManager;
use Zef\Framework\Http\HttpException;

/**
 * The CSRF token core, on its own terms: the two-part shape, the
 * per-token randomness, the secret-bound signature, the shape gate that
 * answers before any mac is computed, and the constant-time comparison
 * -- plus the two construction floors the reference held.
 *
 * @internal
 */
#[CoversClass(CsrfTokenManager::class)]
final class CsrfTokenManagerTest extends TestCase
{
    private const string SECRET = 'test-signing-secret-with-thirty-two-bytes!';

    public function testIssuanceCarriesTheTwoPartShape(): void
    {
        $token = new CsrfTokenManager(self::SECRET)->issue();

        self::assertMatchesRegularExpression(
            '/^[A-Za-z0-9_-]{43}\.[a-f0-9]{64}$/D',
            $token,
            '43 base64url characters for 32 entropy bytes, then the 64-hex mac',
        );
    }

    public function testIssuanceMintsFreshTokens(): void
    {
        $csrfTokenManager = new CsrfTokenManager(self::SECRET);

        $first = $csrfTokenManager->issue();
        $second = $csrfTokenManager->issue();

        self::assertNotSame($first, $second, 'the entropy is fresh per token');
        self::assertNotSame(
            mb_substr($first, 0, (int) mb_strpos($first, '.')),
            mb_substr($second, 0, (int) mb_strpos($second, '.')),
        );
    }

    public function testIssuanceHonoursTheEntropyKnob(): void
    {
        $token = new CsrfTokenManager(self::SECRET, 16)->issue();

        self::assertMatchesRegularExpression(
            '/^[A-Za-z0-9_-]{22}\.[a-f0-9]{64}$/D',
            $token,
            '22 base64url characters for 16 entropy bytes',
        );
    }

    public function testAnIssuedTokenIsValid(): void
    {
        $csrfTokenManager = new CsrfTokenManager(self::SECRET);

        self::assertTrue($csrfTokenManager->isValid($csrfTokenManager->issue()));
    }

    public function testTheSignatureIsBoundToTheSecret(): void
    {
        $token = new CsrfTokenManager(self::SECRET)->issue();

        self::assertFalse(
            new CsrfTokenManager('another-signing-secret-thirty-two-bytes!!')->isValid($token),
            'a token signed by one secret does not verify under another',
        );
    }

    public function testATamperedBodyIsRefused(): void
    {
        $csrfTokenManager = new CsrfTokenManager(self::SECRET);
        $token = $csrfTokenManager->issue();

        [$body, $mac] = explode('.', $token, 2);
        $flipped = ($body[0] === 'A' ? 'B' : 'A') . mb_substr($body, 1);

        self::assertFalse($csrfTokenManager->isValid($flipped . '.' . $mac));
    }

    public function testATamperedSignatureIsRefused(): void
    {
        $csrfTokenManager = new CsrfTokenManager(self::SECRET);
        $token = $csrfTokenManager->issue();

        [$body, $mac] = explode('.', $token, 2);
        $flipped = ($mac[0] === 'a' ? 'b' : 'a') . mb_substr($mac, 1);

        self::assertFalse($csrfTokenManager->isValid($body . '.' . $flipped));
    }

    /**
     * @dataProvider provideMalformedTokensAreRefusedByTheShapeGateCases
     */
    #[DataProvider('provideMalformedTokensAreRefusedByTheShapeGateCases')]
    public function testMalformedTokensAreRefusedByTheShapeGate(string $token): void
    {
        $csrfTokenManager = new CsrfTokenManager(self::SECRET);

        self::assertFalse($csrfTokenManager->isValid($token));
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function provideMalformedTokensAreRefusedByTheShapeGateCases(): iterable
    {
        yield 'no separator' => ['only-a-body'];

        yield 'empty body' => ['.' . str_repeat('b', 63)];

        yield 'empty signature' => ['ABCD.'];

        yield 'second separator' => ['ABCD.EFGH.' . str_repeat('a', 64)];

        yield 'padding byte in the body' => ['AB=CD.' . str_repeat('a', 64)];

        yield 'plus byte in the body' => ['AB+CD.' . str_repeat('a', 64)];

        yield 'uppercase hex in the signature' => ['ABCD.' . mb_strtoupper(str_repeat('a', 64))];

        yield 'short signature' => ['ABCD.' . str_repeat('a', 63)];

        yield 'long signature' => ['ABCD.' . str_repeat('a', 65)];

        yield 'non-hex signature' => ['ABCD.' . str_repeat('g', 64)];

        yield 'empty everything' => [''];
    }

    public function testATrailingSuffixOnASignedTokenIsRefused(): void
    {
        $csrfTokenManager = new CsrfTokenManager(self::SECRET);
        $token = $csrfTokenManager->issue();

        self::assertFalse(
            $csrfTokenManager->isValid($token . '.junk'),
            'a third dot-separated segment makes the signature arm malformed',
        );
    }

    public function testAMalformedBodyCarryingItsOwnMacIsRefused(): void
    {
        $csrfTokenManager = new CsrfTokenManager(self::SECRET);
        $body = 'not-base64url!';
        $mac = hash_hmac('sha256', $body, self::SECRET);

        self::assertFalse(
            $csrfTokenManager->isValid($body . '.' . $mac),
            'the shape gate answers before the mac is computed: a body outside the alphabet never verifies, even against its own mac',
        );
    }

    public function testAShortSecretIsRefusedAtConstruction(): void
    {
        try {
            new CsrfTokenManager('short-secret');
            self::fail('A secret below 32 bytes must refuse the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The csrf signing secret must be at least 32 bytes.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAMultiByteSecretBelowThirtyTwoCharactersIsRefused(): void
    {
        try {
            new CsrfTokenManager(str_repeat('é', 31));
            self::fail('A secret below 32 characters must refuse the construction, whatever its byte length.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The csrf signing secret must be at least 32 bytes.',
                $httpException->getMessage(),
            );
        }
    }

    public function testASecretOfExactlyThirtyTwoBytesSigns(): void
    {
        $csrfTokenManager = new CsrfTokenManager(str_repeat('a', 32));

        self::assertTrue($csrfTokenManager->isValid($csrfTokenManager->issue()), 'the floor admits exactly 32');
    }

    public function testShortEntropyIsRefusedAtConstruction(): void
    {
        try {
            new CsrfTokenManager(self::SECRET, 15);
            self::fail('An entropy below 16 bytes must refuse the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The csrf token entropy must be at least 16 bytes.',
                $httpException->getMessage(),
            );
        }
    }
}
