<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\SessionId;

/**
 * The session family's identifier core, on its own terms: the
 * base64url fold over the entropy the knob asked for, the issuance
 * seam the suite can pin, the uniqueness fresh randomness buys -- and
 * the incoming grammar's alphabet and length corridor, including the
 * corridor's own edges.
 *
 * @internal
 */
#[CoversClass(SessionId::class)]
final class SessionIdTest extends TestCase
{
    public function testAnIssuedIdentifierMatchesItsOwnGrammar(): void
    {
        $identifier = new SessionId()->issue();

        self::assertTrue(SessionId::isValid($identifier), 'the family can always re-read what it issued');
        self::assertSame(43, mb_strlen($identifier), '32 entropy bytes fold to 43 unpadded characters');
    }

    public function testTheEntropyFloorHoldsAtTheSeamItself(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains(
            'The session identifier entropy must be at least 16 bytes.',
        );

        new SessionId(15);
    }

    public function testTwoIssuedIdentifiersNeverCollide(): void
    {
        $sessionId = new SessionId();

        self::assertNotSame($sessionId->issue(), $sessionId->issue());
    }

    public function testTheIssuanceSeamIsPinnable(): void
    {
        $sessionId = new SessionId(32, static fn (int $bytes): string => str_repeat("\x01", $bytes));

        self::assertSame(
            str_repeat('AQEB', 10) . 'AQE',
            $sessionId->issue(),
            'the injectable entropy seam folds deterministically under the pin',
        );
    }

    public function testTheEntropyCorridorsEdgesFold(): void
    {
        self::assertSame(22, mb_strlen(new SessionId(16)->issue()), 'the floor folds to the corridor floor');
        self::assertSame(86, mb_strlen(new SessionId(64)->issue()), 'the ceiling folds to the corridor ceiling');
    }

    #[DataProvider('provideTheGrammarAcceptsCases')]
    public function testTheGrammarAccepts(string $value): void
    {
        self::assertTrue(SessionId::isValid($value));
    }

    /** @return iterable<string, array{string}> */
    public static function provideTheGrammarAcceptsCases(): iterable
    {
        yield 'the floor spelling' => [str_repeat('A', 22)];

        yield 'the ceiling spelling' => [str_repeat('_', 86)];

        yield 'the full alphabet' => ['ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_'];
    }

    #[DataProvider('provideTheGrammarRefusesCases')]
    public function testTheGrammarRefuses(string $value): void
    {
        self::assertFalse(SessionId::isValid($value));
    }

    /** @return iterable<string, array{string}> */
    public static function provideTheGrammarRefusesCases(): iterable
    {
        yield 'the empty value' => [''];

        yield 'one character below the floor' => [str_repeat('A', 21)];

        yield 'one character above the ceiling' => [str_repeat('A', 87)];

        yield 'a dot separator from the CSRF family' => ['AAAAAAAAAAAAAAAAAAAAAA.body'];

        yield 'standard base64 padding' => ['AAAAAAAAAAAAAAAAAAAAAA=='];

        yield 'a standard base64 alphabet member' => ['AAAAAAAAAAAAAAAAAAAAAA+'];

        yield 'a space' => ['AAAAAAAAAAAAAAAAAAAAA '];
    }
}
