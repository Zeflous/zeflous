<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HostAuthority;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Uri;
use Zef\Framework\Http\WorkerHeaders;
use Zef\Framework\Http\WorkerUri;

/**
 * The effective request URI, on its own terms: the absolute spelling
 * riding as-is, the origin form joining the Host authority, and the
 * localhost posture a payload that describes neither authority takes.
 *
 * @internal
 */
#[CoversClass(WorkerUri::class)]
#[CoversClass(WorkerHeaders::class)]
#[CoversClass(HostAuthority::class)]
final class WorkerUriTest extends TestCase
{
    public function testTheAbsoluteSpellingRidesAsIs(): void
    {
        $payloadUri = new Uri('https://api.example.com:8443/v1/users?page=2');

        self::assertSame(
            'https://api.example.com:8443/v1/users?page=2',
            (string) WorkerUri::resolve($payloadUri, WorkerHeaders::normalize([])),
            "the wire's own reconstruction wins the URI, scheme and authority verbatim",
        );
    }

    public function testAProtocolRelativeSpellingCarriesItsOwnAuthority(): void
    {
        $payloadUri = new Uri('//api.example.com/v1/users');

        self::assertSame(
            '//api.example.com/v1/users',
            (string) WorkerUri::resolve($payloadUri, WorkerHeaders::normalize([])),
            'an authority is an authority: the join fold never touches it',
        );
    }

    public function testTheOriginFormJoinsTheHostAuthority(): void
    {
        $payloadUri = new Uri('/v1/users?page=2');
        $headers = WorkerHeaders::normalize(['Host' => 'api.example.com']);

        self::assertSame(
            'http://api.example.com/v1/users?page=2',
            (string) WorkerUri::resolve($payloadUri, $headers),
            "the origin form gains the Host header's authority under the worker's own plain scheme",
        );
    }

    public function testTheOriginFormJoinsANonDefaultAuthorityPort(): void
    {
        $payloadUri = new Uri('/health');
        $headers = WorkerHeaders::normalize(['Host' => 'api.example.com:8080']);

        self::assertSame(
            'http://api.example.com:8080/health',
            (string) WorkerUri::resolve($payloadUri, $headers),
            'the authority port the Host header spells rides the join',
        );
    }

    public function testTheOriginFormJoinsABracketedIPv6Authority(): void
    {
        $payloadUri = new Uri('/health');
        $headers = WorkerHeaders::normalize(['Host' => '[2001:db8::1]:8080']);

        self::assertSame(
            'http://[2001:db8::1]:8080/health',
            (string) WorkerUri::resolve($payloadUri, $headers),
            'the IPv6 literal keeps its brackets in the authority the join renders',
        );
    }

    public function testAnAuthorityLessPayloadWithNoHostTakesTheLocalhostPosture(): void
    {
        $payloadUri = new Uri('/status?probe=live');

        self::assertSame(
            'http://localhost/status?probe=live',
            (string) WorkerUri::resolve($payloadUri, WorkerHeaders::normalize([])),
            "the SAPI twin's CLI-simulation posture, replayed for the worker",
        );
    }

    public function testAnEmptyHostLineAnswersTheLocalhostPosture(): void
    {
        $payloadUri = new Uri('/status');

        self::assertSame(
            'http://localhost/status',
            (string) WorkerUri::resolve($payloadUri, WorkerHeaders::normalize(['Host' => ''])),
            'an empty Host line describes no authority, exactly as absence does',
        );
    }

    public function testTheJoinKeepsTheQueryAbsentWhenTheWireCarriesNone(): void
    {
        $payloadUri = new Uri('/health');
        $headers = WorkerHeaders::normalize(['Host' => 'api.example.com']);

        self::assertSame(
            'http://api.example.com/health',
            (string) WorkerUri::resolve($payloadUri, $headers),
            'no query on the wire means no query in the join',
        );
    }

    public function testTwoHostValuesAreTheImpossibleLineTheGrammarRefuses(): void
    {
        $payloadUri = new Uri('/health');
        $headers = WorkerHeaders::normalize(['Host' => ['api.example.com', 'evil.example.net']]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessageIsOrContains('Malformed Host header.');

        WorkerUri::resolve($payloadUri, $headers);
    }

    public function testAMalformedHostLineIsRefusedByTheAuthorityGrammar(): void
    {
        $payloadUri = new Uri('/health');
        $headers = WorkerHeaders::normalize(['Host' => 'bad host']);

        $this->expectException(HttpException::class);

        WorkerUri::resolve($payloadUri, $headers);
    }
}
