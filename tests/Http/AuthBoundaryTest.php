<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\AllowScopePolicy;
use Zef\Framework\Http\AuthAdmission;
use Zef\Framework\Http\AuthBoundary;
use Zef\Framework\Http\AuthContext;
use Zef\Framework\Http\AuthCredential;
use Zef\Framework\Http\AuthFailure;
use Zef\Framework\Http\AuthRequest;
use Zef\Framework\Http\AuthResult;
use Zef\Framework\Http\AuthStatus;
use Zef\Framework\Http\AuthVerdict;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\ReplayDecision;
use Zef\Framework\Http\ReservedPrincipalName;
use Zef\Test\Support\Http\RecordingCredentialProvider;
use Zef\Test\Support\Http\RecordingReplayProtector;

/**
 * The distributed security boundary, on its own terms: the admission
 * order (authenticate, authorize, check freshness), every
 * non-authenticated status's own failure, the anonymous handle's
 * resolution, the context the allowed admission carries -- plus the
 * result and admission value objects' own constructor invariants.
 *
 * @internal
 */
#[CoversClass(AuthBoundary::class)]
#[CoversClass(AuthFailure::class)]
#[CoversClass(ReservedPrincipalName::class)]
#[CoversClass(AuthAdmission::class)]
#[CoversClass(AuthContext::class)]
#[CoversClass(AuthResult::class)]
final class AuthBoundaryTest extends TestCase
{
    private const int NOW = 1730000000000;

    private RecordingCredentialProvider $recordingCredentialProvider;

    private RecordingReplayProtector $recordingReplayProtector;

    private AuthBoundary $authBoundary;

    #[Override]
    protected function setUp(): void
    {
        $this->recordingCredentialProvider = new RecordingCredentialProvider();
        $this->recordingReplayProtector = new RecordingReplayProtector();
        $this->authBoundary = new AuthBoundary(
            $this->recordingCredentialProvider,
            new AllowScopePolicy('api'),
            $this->recordingReplayProtector,
        );
    }

    /**
     * @dataProvider provideTheNonAuthenticatedStatusesFailTheirOwnWayCases
     */
    #[DataProvider('provideTheNonAuthenticatedStatusesFailTheirOwnWayCases')]
    public function testTheNonAuthenticatedStatusesFailTheirOwnWay(
        AuthStatus $authStatus,
        AuthFailure $authFailure,
    ): void {
        $this->recordingCredentialProvider->result = new AuthResult($authStatus);

        $admission = $this->admit();

        self::assertSame(AuthVerdict::DENY, $admission->verdict);
        self::assertSame($authFailure, $admission->failure);
        self::assertFalse($admission->retryAllowed);
        self::assertNull($admission->context);
        self::assertSame([], $this->recordingReplayProtector->checks, 'no policy or protector ran');
    }

    /**
     * @return iterable<non-empty-string, array{AuthStatus, AuthFailure}>
     */
    public static function provideTheNonAuthenticatedStatusesFailTheirOwnWayCases(): iterable
    {
        yield 'unauthenticated' => [AuthStatus::UNAUTHENTICATED, AuthFailure::AUTHENTICATION_FAILED];

        yield 'failed' => [AuthStatus::FAILED, AuthFailure::AUTHENTICATION_FAILED];

        yield 'expired' => [AuthStatus::EXPIRED, AuthFailure::CREDENTIAL_EXPIRED];

        yield 'unavailable' => [AuthStatus::UNAVAILABLE, AuthFailure::AUTHENTICATION_UNAVAILABLE];
    }

    public function testAnAllowedRequestCarriesItsContext(): void
    {
        $authContext = new AuthContext('service', 'static-bearer', 'route', 'api', null);
        $this->recordingCredentialProvider->result = new AuthResult(AuthStatus::AUTHENTICATED, $authContext);

        $admission = $this->admit();

        self::assertSame(AuthVerdict::ALLOW, $admission->verdict);
        self::assertSame(AuthFailure::NONE, $admission->failure);
        self::assertSame($authContext, $admission->context);
        self::assertTrue($admission->allows());
        self::assertFalse(
            $admission->retryAllowed,
            'an allowed admission is final and offers no retry',
        );
        self::assertSame(
            'table-token-from-the-test-suite',
            $this->recordingCredentialProvider->resolutions[0][0]->handleId,
            'the credential-bearing request resolves through its own handle, never the anonymous one',
        );
        self::assertCount(1, $this->recordingReplayProtector->checks, 'the freshness ran');
    }

    public function testAnAuthorizationRefusalDeniesBeforeFreshness(): void
    {
        $authContext = new AuthContext('service', 'static-bearer', 'route', 'public', null);
        $this->recordingCredentialProvider->result = new AuthResult(AuthStatus::AUTHENTICATED, $authContext);

        $admission = $this->admit();

        self::assertSame(AuthFailure::AUTHORIZATION_DENIED, $admission->failure);
        self::assertSame([], $this->recordingReplayProtector->checks, 'the protector never ran');
    }

    /**
     * @dataProvider provideTheReplayRefusalsCases
     */
    #[DataProvider('provideTheReplayRefusalsCases')]
    public function testTheReplayRefusals(ReplayDecision $replayDecision, AuthFailure $authFailure): void
    {
        $this->recordingCredentialProvider->result = new AuthResult(
            AuthStatus::AUTHENTICATED,
            new AuthContext('service', 'static-bearer', 'route', 'api', null),
        );
        $this->recordingReplayProtector->decision = $replayDecision;

        $admission = $this->admit();

        self::assertSame(AuthVerdict::DENY, $admission->verdict);
        self::assertSame($authFailure, $admission->failure);
    }

    /**
     * @return iterable<non-empty-string, array{ReplayDecision, AuthFailure}>
     */
    public static function provideTheReplayRefusalsCases(): iterable
    {
        yield 'duplicate' => [ReplayDecision::DUPLICATE, AuthFailure::REPLAY_REJECTED];

        yield 'stale' => [ReplayDecision::STALE, AuthFailure::REPLAY_REJECTED];

        yield 'rejected' => [ReplayDecision::REJECTED, AuthFailure::REPLAY_REJECTED];

        yield 'unavailable' => [ReplayDecision::UNAVAILABLE, AuthFailure::REPLAY_UNAVAILABLE];
    }

    /**
     * @dataProvider provideTheReplayPassagesCases
     */
    #[DataProvider('provideTheReplayPassagesCases')]
    public function testTheReplayPassages(ReplayDecision $replayDecision): void
    {
        $this->recordingCredentialProvider->result = new AuthResult(
            AuthStatus::AUTHENTICATED,
            new AuthContext('service', 'static-bearer', 'route', 'api', null),
        );
        $this->recordingReplayProtector->decision = $replayDecision;

        $admission = $this->admit();

        self::assertSame(AuthVerdict::ALLOW, $admission->verdict);
    }

    /**
     * @return iterable<non-empty-string, array{ReplayDecision}>
     */
    public static function provideTheReplayPassagesCases(): iterable
    {
        yield 'not required' => [ReplayDecision::NOT_REQUIRED];

        yield 'accept' => [ReplayDecision::ACCEPT];
    }

    public function testTheNullCredentialResolvesAsTheAnonymousHandle(): void
    {
        $this->authBoundary->admit(null, new AuthRequest('POST /health', '/health', 'POST'), self::NOW);

        self::assertCount(1, $this->recordingCredentialProvider->resolutions);
        self::assertTrue(
            $this->recordingCredentialProvider->resolutions[0][0]->isAnonymous(),
            'the credential-less request resolves through the anonymous handle',
        );
    }

    public function testAPresentedCredentialUnderTheReservedAnonymousNameIsRefusedAsMalformedMetadata(): void
    {
        // Issue #785's finding 4: the anonymous principal's name is
        // reserved for the anonymous resolution -- a provider that
        // resolves a presented credential under it would steer an
        // authenticated principal into the policy's anonymous branch
        // and its blanket allowance, so the boundary refuses the
        // metadata malformation before the policy or protector runs.
        $this->recordingCredentialProvider->result = new AuthResult(
            AuthStatus::AUTHENTICATED,
            new AuthContext('anonymous', 'static-bearer', 'route', 'api', null),
        );

        $admission = $this->admit();

        self::assertSame(AuthVerdict::DENY, $admission->verdict);
        self::assertSame(AuthFailure::MALFORMED_METADATA, $admission->failure);
        self::assertNull($admission->context);
        self::assertSame([], $this->recordingReplayProtector->checks, 'no policy or protector ran');
    }

    /**
     * @dataProvider provideTheReservedAnonymousNameFoldIsRefusedCases
     */
    #[DataProvider('provideTheReservedAnonymousNameFoldIsRefusedCases')]
    public function testTheReservedAnonymousNameFoldIsRefused(string $principalId): void
    {
        $this->recordingCredentialProvider->result = new AuthResult(
            AuthStatus::AUTHENTICATED,
            new AuthContext($principalId, 'static-bearer', 'route', 'api', null),
        );

        $admission = $this->admit();

        self::assertSame(
            AuthFailure::MALFORMED_METADATA,
            $admission->failure,
            'the reference folds the principal id before the anonymous comparison, so every fold is reserved',
        );
    }

    /**
     * @return iterable<non-empty-string, array{string}>
     */
    public static function provideTheReservedAnonymousNameFoldIsRefusedCases(): iterable
    {
        yield 'upper case' => ['ANONYMOUS'];

        yield 'mixed case' => ['AnOnYmOuS'];
    }

    public function testTheAnonymousHandleMayStillAnswerTheReservedName(): void
    {
        // The doctrine's other half: the credential-less resolution
        // keeps the name -- the anonymous handle the boundary itself
        // constructed is the one spelling a provider may legitimately
        // answer `anonymous` for.
        $this->recordingCredentialProvider->result = new AuthResult(
            AuthStatus::AUTHENTICATED,
            new AuthContext('anonymous', 'anonymous', 'route', 'public', null),
        );

        $authBoundary = new AuthBoundary(
            $this->recordingCredentialProvider,
            new AllowScopePolicy('api', true),
            $this->recordingReplayProtector,
        );

        $admission = $authBoundary->admit(null, new AuthRequest('POST /health', '/health', 'POST'), self::NOW);

        self::assertSame(AuthVerdict::ALLOW, $admission->verdict);
        self::assertSame(AuthFailure::NONE, $admission->failure);
        self::assertCount(1, $this->recordingReplayProtector->checks, 'the full gauntlet ran');
    }

    public function testAnExplicitlyPassedAnonymousHandleIsTheAnonymousResolutionToo(): void
    {
        // The discriminator is the handle's own anonymous state, not
        // the argument's nullness: a caller passing the anonymous
        // handle explicitly travels the anonymous resolution, and a
        // provider answering it with the reserved name stays allowed.
        $this->recordingCredentialProvider->result = new AuthResult(
            AuthStatus::AUTHENTICATED,
            new AuthContext('anonymous', 'anonymous', 'route', 'public', null),
        );

        $authBoundary = new AuthBoundary(
            $this->recordingCredentialProvider,
            new AllowScopePolicy('api', true),
            $this->recordingReplayProtector,
        );

        $admission = $authBoundary->admit(
            AuthCredential::anonymous(),
            new AuthRequest('POST /health', '/health', 'POST'),
            self::NOW,
        );

        self::assertSame(AuthVerdict::ALLOW, $admission->verdict);
    }

    public function testTheReservedNameFoldIsCaseInsensitive(): void
    {
        self::assertTrue(ReservedPrincipalName::isClaimedBy('anonymous'));
        self::assertTrue(ReservedPrincipalName::isClaimedBy('ANONYMOUS'));
        self::assertTrue(ReservedPrincipalName::isClaimedBy('AnOnYmOuS'));
    }

    public function testTheReservedNameFoldKeepsOtherSpellings(): void
    {
        self::assertFalse(ReservedPrincipalName::isClaimedBy('service'));
        self::assertFalse(ReservedPrincipalName::isClaimedBy(' anon'));
        self::assertFalse(ReservedPrincipalName::isClaimedBy('anonymous-2'));
    }

    public function testTheAnonymousHandleKeepsTheReservedName(): void
    {
        self::assertFalse(
            ReservedPrincipalName::claimedBeyondTheAnonymousResolution(
                AuthCredential::anonymous(),
                'anonymous',
            ),
            "the anonymous resolution is the reserved name's own spelling",
        );
    }

    public function testAPresentedHandleClaimsTheReservedNameBeyondTheResolution(): void
    {
        self::assertTrue(
            ReservedPrincipalName::claimedBeyondTheAnonymousResolution(
                new AuthCredential('table-token-from-the-test-suite', 'bearer', \PHP_INT_MAX),
                'ANONYMOUS',
            ),
        );
    }

    public function testTheClockTravelsToBothCollaborators(): void
    {
        $this->recordingCredentialProvider->result = new AuthResult(
            AuthStatus::AUTHENTICATED,
            new AuthContext('service', 'static-bearer', 'route', 'api', null),
        );

        $this->admit();

        self::assertSame(self::NOW, $this->recordingCredentialProvider->resolutions[0][1]);
        self::assertSame(self::NOW, $this->recordingReplayProtector->checks[0][1]);
    }

    public function testAContextCarriesItsPeerIdentity(): void
    {
        $authContext = new AuthContext('service', 'static-bearer', 'route', 'api', 'peer-1');

        self::assertSame('peer-1', $authContext->peerIdentity);
    }

    /**
     * @dataProvider provideEveryContextFieldGuardRefusesItsOwnViolationCases
     */
    #[DataProvider('provideEveryContextFieldGuardRefusesItsOwnViolationCases')]
    public function testEveryContextFieldGuardRefusesItsOwnViolation(
        string $message,
        string $principal,
        string $method,
        string $authzContext,
        string $scope,
        ?string $peer,
    ): void {
        try {
            new AuthContext($principal, $method, $authzContext, $scope, $peer);
            self::fail('The field guard must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame($message, $httpException->getMessage());
        }
    }

    /**
     * One case per field, each carrying the context's constructor
     * arguments with exactly that field violated: the empty string
     * violates every field's non-empty bound, and the violation must
     * surface inside the test body, where the guard's rejection is
     * observable -- a violation pre-constructed in the provider would
     * halt the provider itself before the first assertion.
     *
     * @return iterable<non-empty-string, array{string, string, string, string, string, null|string}>
     */
    public static function provideEveryContextFieldGuardRefusesItsOwnViolationCases(): iterable
    {
        yield 'principal id' => [
            'The security.auth context principalId exceeds its bound.',
            '',
            'static-bearer',
            'route',
            'api',
            null,
        ];

        yield 'authentication method' => [
            'The security.auth context authenticationMethod exceeds its bound.',
            'service',
            '',
            'route',
            'api',
            null,
        ];

        yield 'authorization context' => [
            'The security.auth context authorizationContext exceeds its bound.',
            'service',
            'static-bearer',
            '',
            'api',
            null,
        ];

        yield 'credential scope' => [
            'The security.auth context credentialScope exceeds its bound.',
            'service',
            'static-bearer',
            'route',
            '',
            null,
        ];

        yield 'peer identity' => [
            'The security.auth context peerIdentity exceeds its bound.',
            'service',
            'static-bearer',
            'route',
            'api',
            '',
        ];
    }

    public function testAFieldOfExactlyTheContextBoundCarries(): void
    {
        $authContext = new AuthContext(str_repeat('p', 128), 'static-bearer', 'route', 'api', null);

        self::assertSame(str_repeat('p', 128), $authContext->principalId);
    }

    public function testAMultiByteContextFieldIsMeasuredInCharacters(): void
    {
        $authContext = new AuthContext(str_repeat('é', 65), 'static-bearer', 'route', 'api', null);

        self::assertSame(
            str_repeat('é', 65),
            $authContext->principalId,
            '65 characters are 130 bytes, and the bound is 128 characters',
        );
    }

    public function testAnOverLongContextFieldIsRefused(): void
    {
        try {
            new AuthContext(str_repeat('p', 129), 'static-bearer', 'route', 'api', null);
            self::fail('An over-long context field must be refused.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth context principalId exceeds its bound.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheAuthenticatedResultRequiresItsContext(): void
    {
        try {
            new AuthResult(AuthStatus::AUTHENTICATED);
            self::fail('An authenticated result without a context must be refused.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth authenticated result requires a context.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheUnauthenticatedResultCannotCarryAContext(): void
    {
        try {
            new AuthResult(AuthStatus::FAILED, new AuthContext('service', 'static-bearer', 'route', 'api', null));
            self::fail('An unauthenticated result carrying a context must be refused.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth unauthenticated result cannot carry a context.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheAllowedAdmissionCannotCarryAFailure(): void
    {
        try {
            new AuthAdmission(AuthVerdict::ALLOW, AuthFailure::AUTHORIZATION_DENIED, false, $this->context());
            self::fail('An allowed admission carrying a failure must be refused.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth allowed admission cannot carry a failure.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheDeniedAdmissionCannotAllowARetry(): void
    {
        try {
            new AuthAdmission(AuthVerdict::DENY, AuthFailure::AUTHORIZATION_DENIED, true);
            self::fail('A denied admission allowing a retry must be refused.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth denied admission cannot allow a retry.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheAllowedAdmissionRequiresItsContext(): void
    {
        try {
            new AuthAdmission(AuthVerdict::ALLOW, AuthFailure::NONE, false);
            self::fail('An allowed admission without a context must be refused.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth allowed admission requires a context.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * One admission over the composed boundary, carrying a credential
     * by default.
     */
    private function admit(?string $replayId = 'replay-1'): AuthAdmission
    {
        return $this->authBoundary->admit(
            new AuthCredential('table-token-from-the-test-suite', 'bearer', \PHP_INT_MAX),
            new AuthRequest('POST /health', '/health', 'POST', $replayId),
            self::NOW,
        );
    }

    /**
     * One authenticated context for the invariant tests.
     */
    private function context(): AuthContext
    {
        return new AuthContext('service', 'static-bearer', 'route', 'api', null);
    }
}
