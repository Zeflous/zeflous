<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zef\Framework\Http\CompressionVary;
use Zef\Framework\Http\Psr17Factory;

/**
 * The `Vary` walk, on its own terms: the token fold, the idempotency,
 * and the first-spelling preservation the CORS walk owns for `Origin`.
 *
 * @internal
 */
#[CoversClass(CompressionVary::class)]
final class CompressionVaryTest extends TestCase
{
    private readonly Psr17Factory $psr17Factory;

    #[Override]
    protected function setUp(): void
    {
        $this->psr17Factory = new Psr17Factory();
    }

    public function testABareResponseGainsTheSingleToken(): void
    {
        $merged = CompressionVary::merge($this->psr17Factory->createResponse());

        self::assertSame('Accept-Encoding', $merged->getHeaderLine('Vary'));
    }

    public function testTheHandlerTokensSurviveAheadOfIt(): void
    {
        $merged = CompressionVary::merge($this->responseWithVary('Accept, Origin'));

        self::assertSame('Accept, Origin, Accept-Encoding', $merged->getHeaderLine('Vary'));
    }

    public function testTheWalkIsIdempotent(): void
    {
        $once = CompressionVary::merge($this->responseWithVary('Accept'));
        $twice = CompressionVary::merge($once);

        self::assertSame('Accept, Accept-Encoding', $once->getHeaderLine('Vary'));
        self::assertSame($once, $twice, 'a response that already varies on the token answers itself');
    }

    public function testAnySpellingOfTheTokenSatisfiesTheWalk(): void
    {
        $merged = CompressionVary::merge($this->responseWithVary('accept-encoding, Accept'));

        self::assertSame(
            'accept-encoding, Accept',
            $merged->getHeaderLine('Vary'),
            'the first spelling is the spelling kept, and no duplicate is appended',
        );
    }

    public function testAMixedCaseSpellingSatisfiesTheWalk(): void
    {
        $merged = CompressionVary::merge($this->responseWithVary('ACCEPT-ENCODING'));

        self::assertSame('ACCEPT-ENCODING', $merged->getHeaderLine('Vary'));
    }

    public function testDuplicateTokensFoldToTheFirstSpelling(): void
    {
        $merged = CompressionVary::merge($this->responseWithVary('accept, Accept, Origin'));

        self::assertSame('accept, Origin, Accept-Encoding', $merged->getHeaderLine('Vary'));
    }

    public function testAMultibyteTokenDedupsByItsFold(): void
    {
        $merged = CompressionVary::merge($this->responseWithVary('Ärger, ärger, ÄRGER'));

        self::assertSame(
            'Ärger, Accept-Encoding',
            $merged->getHeaderLine('Vary'),
            'the character-scale fold recognises every case pair the byte-scale fold cannot',
        );
    }

    public function testEmptyMembersAreNotTokens(): void
    {
        $merged = CompressionVary::merge($this->responseWithVary('Accept,, Origin, ,'));

        self::assertSame('Accept, Origin, Accept-Encoding', $merged->getHeaderLine('Vary'));
    }

    public function testMultipleVaryLinesFoldToOne(): void
    {
        $merged = CompressionVary::merge($this->responseWithVary('Accept', 'Origin'));

        self::assertSame('Accept, Origin, Accept-Encoding', $merged->getHeaderLine('Vary'));
    }

    private function responseWithVary(string ...$lines): ResponseInterface
    {
        $response = $this->psr17Factory->createResponse();

        foreach ($lines as $line) {
            $message = $response->withAddedHeader('Vary', $line);
            self::assertInstanceOf(
                ResponseInterface::class,
                $message,
                'the with* chain must preserve the response type.',
            );
            $response = $message;
        }

        return $response;
    }
}
