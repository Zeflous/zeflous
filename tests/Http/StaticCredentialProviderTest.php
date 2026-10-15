<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\AuthCredential;
use Zef\Framework\Http\AuthStatus;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\StaticCredentialProvider;

/**
 * The static token table, on its own terms: which tokens resolve,
 * the context they resolve to, the anonymous handle's own question,
 * the never-tabling spelling a presented line can produce, and the
 * composition's own bounds.
 *
 * @internal
 */
#[CoversClass(StaticCredentialProvider::class)]
final class StaticCredentialProviderTest extends TestCase
{
    private const string FIRST_TOKEN = 'Zmlyc3QtdGFibGUtdG9rZW4tZnJvbS10aGUtd2lsZA';

    private const string SECOND_TOKEN = 'c2Vjb25kLXRhYmxlLXRva2VuLWZyb20tdGhlLXdpbGQ';

    public function testATableTokenResolvesToTheConfiguredPrincipal(): void
    {
        $staticCredentialProvider = new StaticCredentialProvider(
            [self::FIRST_TOKEN, self::SECOND_TOKEN],
            'service',
            'api:read',
        );

        $result = $staticCredentialProvider->resolve(
            new AuthCredential(self::FIRST_TOKEN, 'bearer', \PHP_INT_MAX),
            1000,
        );

        self::assertSame(AuthStatus::AUTHENTICATED, $result->status);
        self::assertNotNull($result->context);
        self::assertSame('service', $result->context->principalId);
        self::assertSame('static-bearer', $result->context->authenticationMethod);
        self::assertSame('route', $result->context->authorizationContext);
        self::assertSame('api:read', $result->context->credentialScope);
        self::assertNull($result->context->peerIdentity);
    }

    public function testEveryTableTokenResolves(): void
    {
        $staticCredentialProvider = new StaticCredentialProvider([self::FIRST_TOKEN, self::SECOND_TOKEN]);

        $second = $staticCredentialProvider->resolve(
            new AuthCredential(self::SECOND_TOKEN, 'bearer', \PHP_INT_MAX),
            1000,
        );

        self::assertSame(AuthStatus::AUTHENTICATED, $second->status, 'the second entry is table material too');
    }

    public function testAWrongTokenFails(): void
    {
        $staticCredentialProvider = new StaticCredentialProvider([self::FIRST_TOKEN]);

        $result = $staticCredentialProvider->resolve(
            new AuthCredential(self::SECOND_TOKEN, 'bearer', \PHP_INT_MAX),
            1000,
        );

        self::assertSame(AuthStatus::FAILED, $result->status);
        self::assertNull($result->context);
    }

    public function testATokenOfAnotherLengthFails(): void
    {
        $staticCredentialProvider = new StaticCredentialProvider([self::FIRST_TOKEN]);

        $shorter = $staticCredentialProvider->resolve(
            new AuthCredential(mb_substr(self::FIRST_TOKEN, 0, 40), 'bearer', \PHP_INT_MAX),
            1000,
        );

        self::assertSame(AuthStatus::FAILED, $shorter->status);
    }

    public function testTheAnonymousHandleIsRefusedByDefault(): void
    {
        $staticCredentialProvider = new StaticCredentialProvider([self::FIRST_TOKEN]);

        $result = $staticCredentialProvider->resolve(AuthCredential::anonymous(), 1000);

        self::assertSame(AuthStatus::FAILED, $result->status);
        self::assertNull($result->context);
    }

    public function testTheAnonymousHandleResolvesWhenAllowed(): void
    {
        $staticCredentialProvider = new StaticCredentialProvider([self::FIRST_TOKEN], 'api', 'api', true);

        $result = $staticCredentialProvider->resolve(AuthCredential::anonymous(), 1000);

        self::assertSame(AuthStatus::AUTHENTICATED, $result->status);
        self::assertNotNull($result->context);
        self::assertSame('anonymous', $result->context->principalId);
        self::assertSame('anonymous', $result->context->authenticationMethod);
        self::assertSame('route', $result->context->authorizationContext);
        self::assertSame('public', $result->context->credentialScope);
    }

    public function testAPresentedBearerAnonymousIsRefusedEvenWhenAllowed(): void
    {
        $staticCredentialProvider = new StaticCredentialProvider([self::FIRST_TOKEN], 'api', 'api', true);

        $result = $staticCredentialProvider->resolve(new AuthCredential('anonymous', 'bearer', \PHP_INT_MAX), 1000);

        self::assertSame(
            AuthStatus::FAILED,
            $result->status,
            'presenting a credential opts out of the anonymous resolution',
        );
    }

    public function testAnEmptyTableIsRefused(): void
    {
        try {
            new StaticCredentialProvider([]);
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth static token table requires at least one token.',
                $httpException->getMessage(),
            );
        }
    }

    public function testAnEmptyPrincipalIsRefusedAtComposition(): void
    {
        try {
            new StaticCredentialProvider([self::FIRST_TOKEN], '');
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame('The security.auth context principalId exceeds its bound.', $httpException->getMessage());
        }
    }

    public function testAnOverLongScopeIsRefusedAtComposition(): void
    {
        try {
            new StaticCredentialProvider([self::FIRST_TOKEN], 'api', str_repeat('s', 129));
            self::fail('The bound must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth context credentialScope exceeds its bound.',
                $httpException->getMessage(),
            );
        }
    }
}
