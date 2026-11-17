<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\ForwardedProtocolClaim;
use Zef\Framework\Http\HttpException;

/**
 * The forwarded protocol claim's own battery: the claim vocabulary the
 * #797 round split out of the trusted-proxy fold -- the appended list's
 * last value the only claimant, the case fold to the two spellings the
 * layer's requests carry, and the operator-data refusal for a trusted
 * proxy's nonsense spelling.
 *
 * @internal
 */
#[CoversClass(ForwardedProtocolClaim::class)]
final class ForwardedProtocolClaimTest extends TestCase
{
    public function testTheClaimFoldsCaseInsensitively(): void
    {
        self::assertSame('https', ForwardedProtocolClaim::effective('HTTPS'));
    }

    public function testHttpAnswersHttp(): void
    {
        self::assertSame('http', ForwardedProtocolClaim::effective('http'));
    }

    public function testTheLastValueOfAnAppendedListIsTheOnlyClaimant(): void
    {
        self::assertSame(
            'https',
            ForwardedProtocolClaim::effective('http, https'),
            'the appended end is the position the trusted proxy wrote, every earlier entry client-supplied',
        );
    }

    public function testTheLastValueIsWhitespaceTrimmed(): void
    {
        self::assertSame('https', ForwardedProtocolClaim::effective(' https '));
    }

    public function testANonsenseClaimRefusesLoudlyWithTheValueNamed(): void
    {
        try {
            ForwardedProtocolClaim::effective('banana');
            self::fail('A trusted proxy spelling nonsense must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The X-Forwarded-Proto claim a trusted proxy delivered must be http or https, '
                . "'banana' given.",
                $httpException->getMessage(),
            );
        }
    }

    public function testAnEmptyClaimRefusesAsNonsense(): void
    {
        try {
            ForwardedProtocolClaim::effective('');
            self::fail('A present but empty claim claims nothing and must fail closed.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The X-Forwarded-Proto claim a trusted proxy delivered must be http or https, '
                . "'' given.",
                $httpException->getMessage(),
            );
        }
    }

    public function testTheCaseFoldIsMultibyteAwareAndRefusesTheFoldedSpelling(): void
    {
        // The fold is multibyte by design: the fullwidth capital folds to
        // its lowercase and the refusal names the FOLDED spelling, not the
        // raw header spelling -- the byte-oriented fold would leave the
        // fullwidth letter untouched and the message would carry the
        // unfolded claim instead.
        try {
            ForwardedProtocolClaim::effective('HTTPＳ');
            self::fail('A fullwidth spelling is not the http/https vocabulary.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The X-Forwarded-Proto claim a trusted proxy delivered must be http or https, 'httpｓ' given.",
                $httpException->getMessage(),
            );
        }
    }
}
