<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\AllowScopePolicy;
use Zef\Framework\Http\AuthContext;
use Zef\Framework\Http\AuthorizationResult;
use Zef\Framework\Http\AuthRequest;
use Zef\Framework\Http\AuthVerdict;
use Zef\Framework\Http\HttpException;

/**
 * The scope grant policy, on its own terms: the anonymous branch, the
 * granted list's reading (comma-separated, trimmed, empty entries
 * dropped), the byte-for-byte scope comparison, and the composition's
 * own bounds -- plus the authorization result value object's own
 * constructor invariant.
 *
 * @internal
 */
#[CoversClass(AllowScopePolicy::class)]
#[CoversClass(AuthorizationResult::class)]
final class AllowScopePolicyTest extends TestCase
{
    public function testAGrantedScopeIsAllowed(): void
    {
        $allowScopePolicy = new AllowScopePolicy('api');

        $result = $allowScopePolicy->authorize($this->context('api'), $this->request());

        self::assertSame(AuthVerdict::ALLOW, $result->verdict);
        self::assertSame('scope-granted', $result->policyCode);
    }

    /**
     * @dataProvider provideTheGrantedListReadingCases
     */
    #[DataProvider('provideTheGrantedListReadingCases')]
    public function testTheGrantedListReading(string $credentialScope, bool $allowed): void
    {
        $allowScopePolicy = new AllowScopePolicy('admin');

        $result = $allowScopePolicy->authorize($this->context($credentialScope), $this->request());

        self::assertSame($allowed ? AuthVerdict::ALLOW : AuthVerdict::DENY, $result->verdict);
    }

    /**
     * The granted list's own grammar: entries separated, trimmed,
     * empties dropped -- and the comparison byte-for-byte, the way
     * RFC 6749 holds scope tokens.
     *
     * @return iterable<non-empty-string, array{string, bool}>
     */
    public static function provideTheGrantedListReadingCases(): iterable
    {
        yield 'the exact grant' => ['admin', true];

        yield 'one of several' => ['api, admin', true];

        yield 'padded entries' => [' api , admin ', true];

        yield 'empty entries' => ['api,,admin', true];

        yield 'a trailing separator' => ['api,admin,', true];

        yield 'another scope' => ['api', false];

        yield 'a case-variant scope is not the grant' => ['API, ADMIN', false];

        yield 'a prefix is not the grant' => ['administrator', false];
    }

    public function testTheAnonymousPrincipalIsAllowedWhenThePolicySaysSo(): void
    {
        $allowScopePolicy = new AllowScopePolicy('api', true);

        $result = $allowScopePolicy->authorize($this->context('public', 'anonymous'), $this->request());

        self::assertSame(AuthVerdict::ALLOW, $result->verdict);
        self::assertSame('anonymous-allowed', $result->policyCode);
    }

    public function testTheAnonymousPrincipalIsDeniedByDefault(): void
    {
        $allowScopePolicy = new AllowScopePolicy('api');

        $result = $allowScopePolicy->authorize($this->context('public', 'anonymous'), $this->request());

        self::assertSame(AuthVerdict::DENY, $result->verdict);
        self::assertSame('scope-denied', $result->policyCode);
    }

    public function testTheAnonymousFoldIsCaseInsensitive(): void
    {
        $allowScopePolicy = new AllowScopePolicy('api', true);

        $result = $allowScopePolicy->authorize($this->context('public', 'ANONYMOUS'), $this->request());

        self::assertSame(
            AuthVerdict::ALLOW,
            $result->verdict,
            'the reference folds the principal id before the anonymous comparison',
        );
    }

    public function testAnEmptyRequiredScopeIsRefused(): void
    {
        try {
            new AllowScopePolicy('');
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth required scope must not be empty.', $httpException->getMessage());
        }
    }

    public function testAPolicyCodeOfExactlyTheBoundCarries(): void
    {
        $authorizationResult = new AuthorizationResult(AuthVerdict::ALLOW, str_repeat('c', 128));

        self::assertSame(str_repeat('c', 128), $authorizationResult->policyCode);
    }

    public function testAMultiBytePolicyCodeIsMeasuredInCharacters(): void
    {
        $authorizationResult = new AuthorizationResult(AuthVerdict::DENY, str_repeat('é', 65));

        self::assertSame(
            str_repeat('é', 65),
            $authorizationResult->policyCode,
            '65 characters are 130 bytes, and the bound is 128 characters',
        );
    }

    public function testAnEmptyPolicyCodeIsRefused(): void
    {
        try {
            new AuthorizationResult(AuthVerdict::ALLOW, '');
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth authorization code exceeds its bound.', $httpException->getMessage());
        }
    }

    public function testAnOverLongPolicyCodeIsRefused(): void
    {
        try {
            new AuthorizationResult(AuthVerdict::DENY, str_repeat('c', 129));
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth authorization code exceeds its bound.', $httpException->getMessage());
        }
    }

    /**
     * One authenticated context for the policy's reading.
     */
    private function context(string $credentialScope, string $principal = 'service'): AuthContext
    {
        return new AuthContext(
            $principal,
            'static-bearer',
            'route',
            $credentialScope,
            null,
        );
    }

    /**
     * One authorisation request the policy is asked about.
     */
    private function request(): AuthRequest
    {
        return new AuthRequest('GET /health', '/health', 'GET');
    }
}
