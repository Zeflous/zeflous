<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\FrameworkDefaults;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\SecurityHeadersPolicy;

/**
 * The security-headers knob set, on its own terms: the strict reading of
 * every knob spelling, the unknown-knob rejection, and the drift guard
 * that pins the policy's constructor defaults to the framework profile's
 * own declaration (the two places the defaults may be written -- code in
 * the Http layer for hand construction, code in the kernel layer for the
 * Default layer -- must never disagree).
 *
 * @internal
 */
#[CoversClass(SecurityHeadersPolicy::class)]
#[CoversClass(FrameworkDefaults::class)]
final class SecurityHeadersPolicyTest extends TestCase
{
    public function testAnEmptyMapReadsTheDefaultPosture(): void
    {
        $policy = SecurityHeadersPolicy::fromMap([]);
        $securityHeadersPolicy = new SecurityHeadersPolicy();

        self::assertFalse($policy->hsts);
        self::assertFalse($policy->contentSecurityPolicyEnabled);
        self::assertSame("default-src 'self'; frame-ancestors 'none'; base-uri 'self'", $policy->contentSecurityPolicy);
        self::assertTrue($policy->permissionsPolicy);

        self::assertSame($securityHeadersPolicy->hsts, $policy->hsts);
        self::assertSame($securityHeadersPolicy->contentSecurityPolicyEnabled, $policy->contentSecurityPolicyEnabled);
        self::assertSame($securityHeadersPolicy->contentSecurityPolicy, $policy->contentSecurityPolicy);
        self::assertSame($securityHeadersPolicy->permissionsPolicy, $policy->permissionsPolicy);
    }

    public function testAnAbsentFamilyReadsTheDefaultPosture(): void
    {
        $policy = SecurityHeadersPolicy::fromMap(null);

        self::assertFalse($policy->hsts);
        self::assertFalse($policy->contentSecurityPolicyEnabled);
        self::assertTrue($policy->permissionsPolicy);
    }

    public function testAMapThatIsNotAMapIsRejected(): void
    {
        foreach (['headers', true, 7, 4.5] as $map) {
            try {
                SecurityHeadersPolicy::fromMap($map);
                self::fail(
                    'A security.headers value that is not a map must be rejected, ' . get_debug_type($map) . ' given.',
                );
            } catch (HttpException $httpException) {
                self::assertSame(
                    'The security.headers configuration must be a map, ' . get_debug_type($map) . ' given.',
                    $httpException->getMessage(),
                );
            }
        }
    }

    public function testAnUnknownKnobIsRejectedWithTheKnownList(): void
    {
        try {
            SecurityHeadersPolicy::fromMap(['htss' => true]);
            self::fail('An unknown security.headers knob must be rejected.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security.headers configuration carries the unknown knob 'htss' "
                . '(known knobs: hsts, csp, content_security_policy, permissions_policy).',
                $httpException->getMessage(),
            );
        }
    }

    public function testANonStringKnobNameIsRejected(): void
    {
        try {
            SecurityHeadersPolicy::fromMap([7 => true]);
            self::fail('A non-string security.headers knob name must be rejected.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security.headers configuration carries the unknown knob 'int' "
                . '(known knobs: hsts, csp, content_security_policy, permissions_policy).',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * The strict boolean vocabulary: the closed set of string spellings a
     * toggle accepts, in both cases -- everything else is an error, never
     * a silent false.
     */
    public function testTheStrictBooleanVocabularyIsClosed(): void
    {
        $truthy = ['1', 'true', 'on', 'yes', 'TRUE', 'True', 'ON', 'Yes'];
        foreach ($truthy as $spelling) {
            self::assertTrue(
                SecurityHeadersPolicy::fromMap(['hsts' => $spelling])->hsts,
                "The strict boolean reading must accept the true spelling '{$spelling}'.",
            );
        }

        $falsy = ['0', 'false', 'off', 'no', 'FALSE', 'Off', 'NO', ''];
        foreach ($falsy as $spelling) {
            self::assertFalse(
                SecurityHeadersPolicy::fromMap(['hsts' => $spelling])->hsts,
                "The strict boolean reading must accept the false spelling '{$spelling}'.",
            );
        }
    }

    public function testAToggleOutsideTheVocabularyIsRejected(): void
    {
        foreach (['maybe', 'ture', 'y', '2'] as $spelling) {
            try {
                SecurityHeadersPolicy::fromMap(['hsts' => $spelling]);
                self::fail("A toggle spelling outside the boolean vocabulary must be rejected: '{$spelling}'.");
            } catch (HttpException $httpException) {
                self::assertSame(
                    'The security.headers hsts knob must be a boolean or one of the boolean string '
                    . 'spellings (true/false, on/off, yes/no, 1/0), string given.',
                    $httpException->getMessage(),
                );
            }
        }
    }

    public function testANativeNonBooleanToggleIsRejected(): void
    {
        foreach ([1, 0, 4.5, ['on']] as $value) {
            try {
                SecurityHeadersPolicy::fromMap(['hsts' => $value]);
                self::fail('A native non-boolean toggle value must be rejected, ' . get_debug_type($value) . ' given.');
            } catch (HttpException $httpException) {
                self::assertSame(
                    'The security.headers hsts knob must be a boolean or one of the boolean string '
                    . 'spellings (true/false, on/off, yes/no, 1/0), ' . get_debug_type($value) . ' given.',
                    $httpException->getMessage(),
                );
            }
        }
    }

    public function testTheContentSecurityPolicyKnobAcceptsAStringOverride(): void
    {
        $policy = SecurityHeadersPolicy::fromMap(['content_security_policy' => 'default-src https:']);

        self::assertSame('default-src https:', $policy->contentSecurityPolicy);
    }

    public function testTheContentSecurityPolicyKnobRejectsNonStrings(): void
    {
        foreach ([true, 7, ['default-src']] as $value) {
            try {
                SecurityHeadersPolicy::fromMap(['content_security_policy' => $value]);
                self::fail(
                    'A non-string content_security_policy must be rejected, ' . get_debug_type($value) . ' given.',
                );
            } catch (HttpException $httpException) {
                self::assertSame(
                    'The security.headers content_security_policy knob must be a string, '
                    . get_debug_type($value) . ' given.',
                    $httpException->getMessage(),
                );
            }
        }
    }

    public function testAnEmptyContentSecurityPolicyIsRejected(): void
    {
        try {
            new SecurityHeadersPolicy(contentSecurityPolicy: '');
            self::fail('An empty content_security_policy must be rejected.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.headers content_security_policy knob must be a non-empty string.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheHandBuiltConstructorCarriesTheSameInvariants(): void
    {
        $securityHeadersPolicy = new SecurityHeadersPolicy(
            hsts: true,
            contentSecurityPolicyEnabled: true,
            permissionsPolicy: false,
        );

        self::assertTrue($securityHeadersPolicy->hsts);
        self::assertTrue($securityHeadersPolicy->contentSecurityPolicyEnabled);
        self::assertFalse($securityHeadersPolicy->permissionsPolicy);

        try {
            new SecurityHeadersPolicy(hsts: true, contentSecurityPolicy: '');
            self::fail('The constructor must reject an empty policy string like the map reading does.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.headers content_security_policy knob must be a non-empty string.',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * The drift guard: the framework profile's `security.headers` block and
     * the policy's own defaults are two declarations of one posture --
     * this pin keeps them from ever disagreeing, key for key and value for
     * value.
     */
    public function testTheFrameworkProfileMirrorsThePolicyDefaults(): void
    {
        $fromEmptyMap = SecurityHeadersPolicy::fromMap([]);
        $security = FrameworkDefaults::profile()['security'] ?? null;
        self::assertIsArray($security);
        $fromDefaults = $security['headers'] ?? null;
        self::assertIsArray($fromDefaults);

        self::assertSame(
            [
                'hsts' => $fromEmptyMap->hsts,
                'csp' => $fromEmptyMap->contentSecurityPolicyEnabled,
                'content_security_policy' => $fromEmptyMap->contentSecurityPolicy,
                'permissions_policy' => $fromEmptyMap->permissionsPolicy,
            ],
            $fromDefaults,
            'FrameworkDefaults::profile() security.headers and the policy defaults must not drift apart.',
        );
    }
}
