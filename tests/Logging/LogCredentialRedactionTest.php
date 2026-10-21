<?php

declare(strict_types=1);

namespace Zef\Test\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Logging\LogCredentialRedaction;

/**
 * @internal
 */
#[CoversClass(LogCredentialRedaction::class)]
final class LogCredentialRedactionTest extends TestCase
{
    /**
     * Any authorization scheme an `=`/`:` separator introduces: prose
     * never carries the separator, so a sentence keeps its diagnostics.
     */
    public function testAnAuthorizationPairLosesItsCredential(): void
    {
        self::assertSame(
            'authorization: [REDACTED]',
            LogCredentialRedaction::redact('authorization: Mutual 9f86d081884c7d65'),
        );
        self::assertSame(
            'Authorization=[REDACTED]',
            LogCredentialRedaction::redact('Authorization=Digest username="u"'),
        );
    }

    /**
     * The two schemes a bare whitespace may introduce.
     */
    public function testTheAuthorizationSchemesInProseLoseTheirCredential(): void
    {
        self::assertSame(
            'authorization [REDACTED]',
            LogCredentialRedaction::redact('authorization Bearer abc123def45'),
        );
    }

    /**
     * The bare scheme word a credential-shaped value follows.
     */
    public function testABareSchemeCredentialWithoutTheAuthorizationWordIsRedacted(): void
    {
        self::assertSame('Bearer [REDACTED]', LogCredentialRedaction::redact('Bearer eyJhbGciOiJIUzI1NiJ9.e30.abc123'));
        self::assertSame('Basic [REDACTED]', LogCredentialRedaction::redact('Basic dXNlcm5hbWU6cGFzc3dvcmQ='));
        self::assertSame('Basic usage keeps its prose', LogCredentialRedaction::redact('Basic usage keeps its prose'));
    }

    /**
     * The vocabulary pairs whose values are secrets themselves.
     */
    public function testTheVocabularyPairsLoseTheirValues(): void
    {
        self::assertSame('password=[REDACTED]', LogCredentialRedaction::redact('password=hunter2000'));
        self::assertSame(
            'the api_key=[REDACTED] rode',
            LogCredentialRedaction::redact('the api_key=sk-live-991177 rode'),
        );
    }

    /**
     * The session-id grammar a URL query rides.
     */
    public function testASessionIdRidingAQuerySurvivesNothing(): void
    {
        self::assertSame(
            'https://app.example.test/dashboard?PHPSESSID=[REDACTED]',
            LogCredentialRedaction::redact('https://app.example.test/dashboard?PHPSESSID=abc123def456'),
        );
        self::assertSame(
            'https://app.example.test/dashboard?session_id=[REDACTED]',
            LogCredentialRedaction::redact('https://app.example.test/dashboard?session_id=abc123def456'),
        );
    }

    public function testAValueShorterThanTheVocabularyFloorIsNotTouched(): void
    {
        self::assertSame('token=abc', LogCredentialRedaction::redact('token=abc'));
    }

    public function testAnOrdinarySentenceKeepsItsDiagnostics(): void
    {
        self::assertSame(
            'the authorization boundary refused the peer',
            LogCredentialRedaction::redact('the authorization boundary refused the peer'),
        );
    }
}
