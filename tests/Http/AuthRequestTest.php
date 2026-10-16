<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\AuthAttributes;
use Zef\Framework\Http\AuthRequest;
use Zef\Framework\Http\HttpException;

/**
 * The authorisation request value object, on its own terms: the four
 * labelled fields' bounds, and the attribute table's census -- the
 * entry, key, value and total bounds, and the sensitive-material
 * refusal the reference spelled.
 *
 * @internal
 */
#[CoversClass(AuthRequest::class)]
#[CoversClass(AuthAttributes::class)]
final class AuthRequestTest extends TestCase
{
    public function testTheCanonicalShapeCarries(): void
    {
        $authRequest = new AuthRequest('POST /health', '/health', 'POST', 'replay-1', ['tenant' => 'acme']);

        self::assertSame('POST /health', $authRequest->operationClass);
        self::assertSame('/health', $authRequest->resource);
        self::assertSame('POST', $authRequest->action);
        self::assertSame('replay-1', $authRequest->replayId);
        self::assertSame(['tenant' => 'acme'], $authRequest->attributes);
    }

    /**
     * @dataProvider provideTheLabelledFieldsBoundsCases
     */
    #[DataProvider('provideTheLabelledFieldsBoundsCases')]
    public function testTheLabelledFieldsBounds(
        string $operationClass,
        string $resource,
        string $action,
        string $message,
    ): void {
        try {
            new AuthRequest($operationClass, $resource, $action);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth request ' . $message, $httpException->getMessage());
        }
    }

    /**
     * @return iterable<non-empty-string, array{string, string, string, string}>
     */
    public static function provideTheLabelledFieldsBoundsCases(): iterable
    {
        yield 'empty operation' => ['', '/health', 'POST', 'operationClass exceeds its bound.'];

        yield 'over-long operation' => [
            str_repeat('o', 129),
            '/health',
            'POST',
            'operationClass exceeds its bound.',
        ];

        yield 'empty resource' => ['POST /health', '', 'POST', 'resource exceeds its bound.'];

        yield 'over-long resource' => ['POST /health', str_repeat('r', 129), 'POST', 'resource exceeds its bound.'];

        yield 'empty action' => ['POST /health', '/health', '', 'action exceeds its bound.'];

        yield 'over-long action' => ['POST /health', '/health', str_repeat('a', 65), 'action exceeds its bound.'];
    }

    public function testAnOverLongReplayIdIsRefused(): void
    {
        try {
            new AuthRequest('POST /health', '/health', 'POST', str_repeat('r', 129));
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth request replayId exceeds its bound.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAReplayIdOfExactlyTheBoundCarries(): void
    {
        $authRequest = new AuthRequest('POST /health', '/health', 'POST', str_repeat('r', 128));

        self::assertSame(str_repeat('r', 128), $authRequest->replayId);
    }

    public function testTooManyAttributesAreRefused(): void
    {
        $attributes = [];

        for ($entry = 0; $entry < 17; ++$entry) {
            $attributes["key-{$entry}"] = 'x';
        }

        try {
            new AuthRequest('POST /health', '/health', 'POST', null, $attributes);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth request carries too many attributes.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * @dataProvider provideTheSensitiveKeyCensusCases
     */
    #[DataProvider('provideTheSensitiveKeyCensusCases')]
    public function testTheSensitiveKeyCensus(string $key): void
    {
        try {
            new AuthRequest('POST /health', '/health', 'POST', null, [$key => 'value']);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth request attributes cannot carry sensitive credential material.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * The reference's own census: each forbidden fragment, whole and
     * as a fragment, folded before the comparison.
     *
     * @return iterable<non-empty-string, array{string}>
     */
    public static function provideTheSensitiveKeyCensusCases(): iterable
    {
        yield 'authorization' => ['authorization'];

        yield 'proxy authorization' => ['proxy-authorization'];

        yield 'token' => ['token'];

        yield 'secret' => ['secret'];

        yield 'password' => ['password'];

        yield 'private key, underscore' => ['private_key'];

        yield 'private key, hyphen' => ['private-key'];

        yield 'a fragment carrier' => ['the-api-token-id'];

        yield 'a folded fragment carrier' => ['THE-API-SECRET-ID'];

        yield 'a folded whole word' => ['PASSWORD'];
    }

    /**
     * @dataProvider provideTheAttributeShapeCensusCases
     */
    #[DataProvider('provideTheAttributeShapeCensusCases')]
    public function testTheAttributeShapeCensus(mixed $value, string $message): void
    {
        try {
            new AuthRequest('POST /health', '/health', 'POST', null, ['entry' => $value]);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }

    /**
     * @return iterable<non-empty-string, array{mixed, string}>
     */
    public static function provideTheAttributeShapeCensusCases(): iterable
    {
        yield 'a non-scalar value' => [
            ['nested' => 'array'],
            'The security.auth request attributes must be scalar or null.',
        ];

        yield 'an over-long value' => [
            str_repeat('v', 257),
            'The security.auth request attribute value exceeds its bound.',
        ];
    }

    public function testAnInvalidAttributeKeyIsRefused(): void
    {
        try {
            new AuthRequest('POST /health', '/health', 'POST', null, ['k' => 'v', '' => 'empty']);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth request carries an invalid attribute key.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAnOverLongAttributeKeyIsRefused(): void
    {
        try {
            new AuthRequest('POST /health', '/health', 'POST', null, [str_repeat('k', 65) => 'v']);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth request carries an invalid attribute key.',
                $httpException->getMessage(),
            );
        }
    }

    public function testFromRequestBuildsTheLabels(): void
    {
        $authRequest = AuthRequest::fromRequest('GET', '/health', '');

        self::assertSame('GET /health', $authRequest->operationClass);
        self::assertSame('/health', $authRequest->resource);
        self::assertSame('GET', $authRequest->action);
        self::assertNull($authRequest->replayId, 'the empty replay line is no protection asked');
    }

    public function testFromRequestCarriesTheReplayId(): void
    {
        $authRequest = AuthRequest::fromRequest('POST', '/health', 'replay-1');

        self::assertSame('replay-1', $authRequest->replayId);
    }

    public function testFromRequestTruncatesTheLabelsToTheirBounds(): void
    {
        $path = '/' . str_repeat('segment', 19);

        $authRequest = AuthRequest::fromRequest('GET', $path, '');

        self::assertSame(128, mb_strlen($authRequest->operationClass), 'the operation label is cut to its bound');
        self::assertSame(128, mb_strlen($authRequest->resource), 'the resource is cut to its bound');
        self::assertSame(mb_strcut($path, 0, 128), $authRequest->resource);
    }

    public function testFromRequestAnswersTheEmptyPathAsTheRootResource(): void
    {
        $authRequest = AuthRequest::fromRequest('GET', '', '');

        self::assertSame('/', $authRequest->resource);
    }

    public function testAFieldOfExactlyTheBoundCarries(): void
    {
        $authRequest = new AuthRequest('POST /health', '/health', str_repeat('a', 64));

        self::assertSame(str_repeat('a', 64), $authRequest->action);
    }

    public function testAMultiByteFieldIsMeasuredInCharacters(): void
    {
        $authRequest = new AuthRequest('POST /health', '/health', str_repeat('é', 33));

        self::assertSame(
            str_repeat('é', 33),
            $authRequest->action,
            '33 characters are 66 bytes, and the bound is 64 characters',
        );
    }

    public function testAnIntegerAttributeKeyIsRefused(): void
    {
        try {
            new AuthRequest('POST /health', '/health', 'POST', null, [7 => 'v']);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth request carries an invalid attribute key.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheAttributeBudgetIsHeld(): void
    {
        $attributes = [];

        for ($entry = 0; $entry < 16; ++$entry) {
            $attributes["key-{$entry}"] = str_repeat('v', 256);
        }

        try {
            new AuthRequest('POST /health', '/health', 'POST', null, $attributes);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth request attributes exceed their budget.', $httpException->getMessage());
        }
    }

    public function testAMultiByteAttributeKeyIsMeasuredInCharacters(): void
    {
        $authRequest = new AuthRequest('POST /health', '/health', 'POST', null, [str_repeat('é', 33) => 'v']);

        self::assertCount(
            1,
            $authRequest->attributes,
            '33 characters are 66 bytes, and the key bound is 64 characters',
        );
    }

    public function testAMultiByteAttributeValueIsMeasuredInCharacters(): void
    {
        $authRequest = new AuthRequest('POST /health', '/health', 'POST', null, ['k' => str_repeat('é', 129)]);

        self::assertCount(
            1,
            $authRequest->attributes,
            '129 characters are 258 bytes, and the value bound is 256 characters',
        );
    }

    public function testTheAttributeBudgetCountsCharactersNotBytes(): void
    {
        $attributes = [];

        for ($entry = 0; $entry < 16; ++$entry) {
            $attributes["key-{$entry}"] = str_repeat('é', 250);
        }

        $authRequest = new AuthRequest('POST /health', '/health', 'POST', null, $attributes);

        self::assertCount(
            16,
            $authRequest->attributes,
            '4080 characters are 8080 bytes, and the budget is 4096 characters',
        );
    }

    public function testTheAttributeBudgetHoldsAtItsExactEdge(): void
    {
        $attributes = [];

        for ($entry = 0; $entry < 12; ++$entry) {
            $attributes[mb_str_pad((string) $entry, 63, 'k')] = str_repeat('v', 256);
        }

        $attributes[mb_str_pad('12', 63, 'k')] = str_repeat('v', 206);

        try {
            new AuthRequest('POST /health', '/health', 'POST', null, $attributes);
            self::fail('A table one character over the budget must be refused.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth request attributes exceed their budget.', $httpException->getMessage());
        }
    }

    public function testTheAttributeBudgetAdmitsItsFullShape(): void
    {
        $attributes = [];

        for ($entry = 0; $entry < 16; ++$entry) {
            $attributes["key-{$entry}"] = str_repeat('v', 240);
        }

        $authRequest = new AuthRequest('POST /health', '/health', 'POST', null, $attributes);

        self::assertCount(16, $authRequest->attributes);
    }

    public function testTheAttributeBudgetCarriesItsExactEdge(): void
    {
        $attributes = [];

        for ($entry = 0; $entry < 16; ++$entry) {
            $attributes[str_repeat('k', 61) . dechex($entry)] = str_repeat('v', 194);
        }

        $authRequest = new AuthRequest('POST /health', '/health', 'POST', null, $attributes);

        self::assertCount(
            16,
            $authRequest->attributes,
            '16 keys of 62 characters and 16 values of 194 characters are exactly the 4096-character budget',
        );
    }

    public function testAMultiByteAttributeKeyHoldsTheTableUnderItsBudget(): void
    {
        $attributes = [];

        for ($entry = 0; $entry < 16; ++$entry) {
            $attributes[str_repeat('é', 63) . dechex($entry)] = str_repeat('v', 190);
        }

        $authRequest = new AuthRequest('POST /health', '/health', 'POST', null, $attributes);

        self::assertCount(
            16,
            $authRequest->attributes,
            'the keys sit at their own 64-character bound and spend 1024'
                . ' characters of the 4096-character budget even though they spend 2032 bytes',
        );
    }

    public function testAKelvinFoldedKeyStillSpellsCredentialMaterial(): void
    {
        $keyWithKelvinSign = 'to' . "\u{212A}" . 'en';

        try {
            new AuthRequest('POST /health', '/health', 'POST', null, [$keyWithKelvinSign => 'v']);
            self::fail('A key whose Unicode fold spells the token fragment must be refused.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth request attributes cannot carry sensitive credential material.',
                $httpException->getMessage(),
                'the Kelvin sign lower-folds onto the ASCII k, so the folded key spells the forbidden token fragment',
            );
        }
    }

    public function testTheLabelsAreTruncatedOnTheByteBudgetWithoutSplittingACharacter(): void
    {
        $method = str_repeat('a', 63) . str_repeat('Ö', 33);
        $path = str_repeat('a', 127) . str_repeat('Ö', 5);

        $authRequest = AuthRequest::fromRequest($method, $path, 'replay-1');

        self::assertSame(
            str_repeat('a', 63) . str_repeat('Ö', 32),
            $authRequest->operationClass,
            'the operation label carries 127 bytes: the 128th byte is the first'
                . ' half of a 33rd Ö, and mb_strcut backs off to the character boundary',
        );
        self::assertSame(
            str_repeat('a', 127),
            $authRequest->resource,
            'the resource label carries 127 bytes: the 128th byte is'
                . ' the first half of an Ö, and the cut backs off to the boundary',
        );
        self::assertSame(
            str_repeat('a', 63),
            $authRequest->action,
            'the action label carries 63 bytes: the 64th byte is the'
                . ' first half of an Ö, and the cut backs off to the boundary',
        );
        self::assertSame('replay-1', $authRequest->replayId);
    }

    public function testScalarAndNullAttributesCarry(): void
    {
        $authRequest = new AuthRequest('POST /health', '/health', 'POST', null, [
            'a-string' => 'value',
            'an-int' => 7,
            'a-float' => 0.5,
            'a-bool' => true,
            'a-null' => null,
        ]);

        self::assertCount(5, $authRequest->attributes);
    }

    public function testTheMalformedReplayIdShapes(): void
    {
        self::assertTrue(AuthRequest::carriesAMalformedReplayId(''), 'the empty spelling is malformed');
        self::assertTrue(
            AuthRequest::carriesAMalformedReplayId(str_repeat('r', 129)),
            'a 129-character id is over the ceiling',
        );
    }

    public function testTheWellFormedReplayIdShapes(): void
    {
        self::assertFalse(AuthRequest::carriesAMalformedReplayId('replay-1'), 'an ordinary id is well formed');
        self::assertFalse(
            AuthRequest::carriesAMalformedReplayId(str_repeat('r', 128)),
            'an id of exactly the ceiling is well formed',
        );
        self::assertFalse(
            AuthRequest::carriesAMalformedReplayId(str_repeat('é', 65)),
            '65 characters are 130 bytes, and the ceiling is 128 characters',
        );
        self::assertFalse(
            AuthRequest::carriesAMalformedReplayId(str_repeat('é', 64)),
            '64 characters are 128 bytes, and the ceiling is 128 characters',
        );
    }
}
