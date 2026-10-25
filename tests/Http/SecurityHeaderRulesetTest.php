<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\SecurityHeaderCheck;
use Zef\Framework\Http\SecurityHeaderGrammar;
use Zef\Framework\Http\SecurityHeaderRuleset;

/**
 * The security-header audit's declared ruleset, pinned: the ten
 * checks in audit order -- the six unconditional hardening pairs the
 * baseline family stamps, the Content-Security-Policy and
 * https-gated Strict-Transport-Security toggles, the default-on
 * Permissions-Policy, and the advisory Cross-Origin-Embedder-Policy
 * the framework deliberately does not ship -- so the audit surface
 * cannot drift silently.
 *
 * @internal
 */
#[CoversClass(SecurityHeaderRuleset::class)]
#[CoversClass(SecurityHeaderCheck::class)]
final class SecurityHeaderRulesetTest extends TestCase
{
    public function testTheRulesetDeclaresItsChecksInAuditOrder(): void
    {
        self::assertSame(
            [
                'X-Content-Type-Options',
                'X-Frame-Options',
                'Referrer-Policy',
                'X-Permitted-Cross-Domain-Policies',
                'Cross-Origin-Opener-Policy',
                'Cross-Origin-Resource-Policy',
                'Content-Security-Policy',
                'Strict-Transport-Security',
                'Permissions-Policy',
                'Cross-Origin-Embedder-Policy',
            ],
            array_map(
                static fn (SecurityHeaderCheck $securityHeaderCheck): string => $securityHeaderCheck->name,
                SecurityHeaderRuleset::checks(),
            ),
        );
    }

    public function testExactlyOneCheckIsAdvisoryAndExactlyOneIsHttpsOnly(): void
    {
        $advisory = [];

        foreach (SecurityHeaderRuleset::checks() as $securityHeaderCheck) {
            if ($securityHeaderCheck->required) {
                continue;
            }

            $advisory[] = $securityHeaderCheck->name;
        }

        $httpsOnly = [];

        foreach (SecurityHeaderRuleset::checks() as $securityHeaderCheck) {
            if (!$securityHeaderCheck->httpsOnly) {
                continue;
            }

            $httpsOnly[] = $securityHeaderCheck->name;
        }

        self::assertSame(['Cross-Origin-Embedder-Policy'], $advisory);
        self::assertSame(['Strict-Transport-Security'], $httpsOnly);
    }

    public function testEachCheckCarriesItsOwnValueGrammar(): void
    {
        $byName = [];

        foreach (SecurityHeaderRuleset::checks() as $securityHeaderCheck) {
            $byName[$securityHeaderCheck->name] = $securityHeaderCheck;
        }

        self::assertSame(SecurityHeaderCheck::KIND_EXACT, $byName['X-Content-Type-Options']->kind);
        self::assertSame(['nosniff'], $byName['X-Content-Type-Options']->allowedValues);

        self::assertSame(SecurityHeaderCheck::KIND_ONE_OF, $byName['X-Frame-Options']->kind);
        self::assertSame(['DENY', 'SAMEORIGIN'], $byName['X-Frame-Options']->allowedValues);

        self::assertSame(SecurityHeaderCheck::KIND_ONE_OF, $byName['Referrer-Policy']->kind);
        self::assertSame(
            [
                'no-referrer',
                'no-referrer-when-downgrade',
                'origin',
                'origin-when-cross-origin',
                'same-origin',
                'strict-origin',
                'strict-origin-when-cross-origin',
            ],
            $byName['Referrer-Policy']->allowedValues,
        );

        self::assertSame(SecurityHeaderCheck::KIND_EXACT, $byName['X-Permitted-Cross-Domain-Policies']->kind);
        self::assertSame(['none'], $byName['X-Permitted-Cross-Domain-Policies']->allowedValues);

        self::assertSame(SecurityHeaderCheck::KIND_ONE_OF, $byName['Cross-Origin-Opener-Policy']->kind);
        self::assertSame(
            ['same-origin', 'same-origin-allow-popups', 'noopener-allow-popups'],
            $byName['Cross-Origin-Opener-Policy']->allowedValues,
        );

        self::assertSame(SecurityHeaderCheck::KIND_ONE_OF, $byName['Cross-Origin-Resource-Policy']->kind);
        self::assertSame(
            ['same-origin', 'same-site', 'cross-origin'],
            $byName['Cross-Origin-Resource-Policy']->allowedValues,
        );

        self::assertSame(SecurityHeaderCheck::KIND_NON_EMPTY, $byName['Content-Security-Policy']->kind);
        self::assertSame(SecurityHeaderCheck::KIND_MAX_AGE, $byName['Strict-Transport-Security']->kind);
        self::assertSame(SecurityHeaderCheck::KIND_NON_EMPTY, $byName['Permissions-Policy']->kind);
    }

    public function testEveryAllowedValueSatisfiesItsOwnCheckGrammar(): void
    {
        foreach (SecurityHeaderRuleset::checks() as $securityHeaderCheck) {
            foreach ($securityHeaderCheck->allowedValues as $allowedValue) {
                self::assertTrue(
                    SecurityHeaderGrammar::accepts($securityHeaderCheck, $allowedValue),
                    $securityHeaderCheck->name . ' must accept its own declared value "' . $allowedValue . '".',
                );
            }
        }
    }

    public function testEveryCheckCarriesAGuidanceNote(): void
    {
        foreach (SecurityHeaderRuleset::checks() as $securityHeaderCheck) {
            self::assertGreaterThanOrEqual(
                1,
                mb_strlen($securityHeaderCheck->note),
                $securityHeaderCheck->name . ' must carry a guidance note.',
            );
        }
    }
}
