<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\SecurityHeaderAudit;
use Zef\Framework\Http\SecurityHeaderCheck;
use Zef\Framework\Http\SecurityHeaderFinding;
use Zef\Framework\Http\SecurityHeaderGrammar;

/**
 * The security-header audit's walk: every verdict state over the
 * framework's own default posture, every value grammar's pass and
 * fail arm, the https-only applicability rule, the tier rule
 * (advisory findings never fail), and the case-insensitive,
 * joined-duplicate honesty of the field reads.
 *
 * @internal
 */
#[CoversClass(SecurityHeaderAudit::class)]
#[CoversClass(SecurityHeaderFinding::class)]
final class SecurityHeaderAuditTest extends TestCase
{
    public function testTheFrameworksOwnDefaultPostureAnswersItsHonestVerdictsOverHttp(): void
    {
        $response = new Response(200, [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=()',
        ]);

        $audit = SecurityHeaderAudit::over($response, 'http');

        self::assertFalse($audit->passed());

        $byVerdict = [];

        foreach ($audit->findings as $finding) {
            $byVerdict[$finding->verdict][] = $finding->header;
        }

        self::assertContains('Content-Security-Policy', $byVerdict[SecurityHeaderFinding::VERDICT_MISSING]);
        self::assertContains(
            'Strict-Transport-Security',
            $byVerdict[SecurityHeaderFinding::VERDICT_NOT_APPLICABLE],
        );
        self::assertContains('Cross-Origin-Embedder-Policy', $byVerdict[SecurityHeaderFinding::VERDICT_MISSING]);
        self::assertContains('X-Content-Type-Options', $byVerdict[SecurityHeaderFinding::VERDICT_PASS]);
    }

    public function testAFullyHardenedResponseOverHttpsPassesEveryCheck(): void
    {
        $response = new Response(200, [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-site',
            'Content-Security-Policy' => "default-src 'self'",
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Permissions-Policy' => 'geolocation=()',
            'Cross-Origin-Embedder-Policy' => 'require-corp',
        ]);

        $audit = SecurityHeaderAudit::over($response, 'https');

        self::assertTrue($audit->passed());

        foreach ($audit->findings as $finding) {
            self::assertSame(
                SecurityHeaderFinding::VERDICT_PASS,
                $finding->verdict,
                $finding->header . ' must pass.',
            );
        }
    }

    public function testAnInvalidValueFailsTheAuditAtTheRequiredTier(): void
    {
        $response = new Response(200, [
            'X-Content-Type-Options' => 'allow',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Content-Security-Policy' => "default-src 'self'",
            'Strict-Transport-Security' => 'max-age=31536000',
            'Permissions-Policy' => 'camera=()',
        ]);

        $audit = SecurityHeaderAudit::over($response, 'https');

        self::assertFalse($audit->passed());

        $invalid = [];

        foreach ($audit->findings as $finding) {
            if ($finding->verdict !== SecurityHeaderFinding::VERDICT_INVALID) {
                continue;
            }

            $invalid[] = $finding->header;
        }

        self::assertSame(['X-Content-Type-Options'], $invalid);
        self::assertSame('allow', $audit->findings[0]->actual);
        self::assertSame('nosniff', $audit->findings[0]->expected);
    }

    public function testEveryGrammarAnswersItsOwnFailArm(): void
    {
        $exact = new SecurityHeaderCheck('X-Exact', SecurityHeaderCheck::KIND_EXACT, ['a'], true, false, 'n');
        $oneOf = new SecurityHeaderCheck(
            'X-One-Of',
            SecurityHeaderCheck::KIND_ONE_OF,
            ['a', 'b'],
            true,
            false,
            'n',
        );
        $nonEmpty = new SecurityHeaderCheck('X-Non-Empty', SecurityHeaderCheck::KIND_NON_EMPTY, [], true, false, 'n');
        $maxAge = new SecurityHeaderCheck(
            'X-Max-Age',
            SecurityHeaderCheck::KIND_MAX_AGE,
            [],
            true,
            false,
            'n',
        );
        $unknown = new SecurityHeaderCheck('X-Unknown', 'no-such-kind', [], true, false, 'n');

        self::assertTrue(SecurityHeaderGrammar::accepts($exact, 'a'));
        self::assertFalse(SecurityHeaderGrammar::accepts($exact, 'b'));
        self::assertTrue(SecurityHeaderGrammar::accepts($oneOf, 'b'));
        self::assertFalse(SecurityHeaderGrammar::accepts($oneOf, 'c'));
        self::assertTrue(SecurityHeaderGrammar::accepts($nonEmpty, 'v'));
        self::assertFalse(SecurityHeaderGrammar::accepts($nonEmpty, ''));
        self::assertTrue(SecurityHeaderGrammar::accepts($maxAge, 'max-age=1'));
        self::assertFalse(SecurityHeaderGrammar::accepts($maxAge, 'includeSubDomains'));
        self::assertFalse(SecurityHeaderGrammar::accepts($maxAge, 'max-age='));
        self::assertFalse(SecurityHeaderGrammar::accepts($maxAge, 'max-age=1x'));
        self::assertFalse(SecurityHeaderGrammar::accepts($unknown, 'a'));
    }

    public function testTheMaxAgeGrammarIsAnchoredAndRefusesTrailingBytes(): void
    {
        $securityHeaderCheck = new SecurityHeaderCheck(
            'X-Max-Age',
            SecurityHeaderCheck::KIND_MAX_AGE,
            [],
            true,
            false,
            'n',
        );

        // The caret anchors the directive to the value's start: a
        // prefix that merely contains a valid directive is not one.
        self::assertFalse(SecurityHeaderGrammar::accepts($securityHeaderCheck, 'includeSubDomains; max-age=1'));

        // The D modifier anchors the dollar to the value's end: a
        // trailing newline is a byte the wire never carries silently.
        self::assertFalse(SecurityHeaderGrammar::accepts($securityHeaderCheck, "max-age=1\n"));

        // The directive list is accepted after the max-age directive.
        self::assertTrue(SecurityHeaderGrammar::accepts($securityHeaderCheck, 'max-age=1; includeSubDomains'));
    }

    public function testEveryGrammarSpellsItsOwnExpectedDetail(): void
    {
        $exact = new SecurityHeaderCheck('X-Exact', SecurityHeaderCheck::KIND_EXACT, ['a'], true, false, 'n');
        $oneOf = new SecurityHeaderCheck('X-One-Of', SecurityHeaderCheck::KIND_ONE_OF, ['a', 'b'], true, false, 'n');
        $nonEmpty = new SecurityHeaderCheck('X-Non-Empty', SecurityHeaderCheck::KIND_NON_EMPTY, [], true, false, 'n');
        $maxAge = new SecurityHeaderCheck('X-Max-Age', SecurityHeaderCheck::KIND_MAX_AGE, [], true, false, 'n');
        $unknown = new SecurityHeaderCheck('X-Unknown', 'no-such-kind', [], true, false, 'n');

        self::assertSame('a', SecurityHeaderFinding::pass($exact, 'a')->expected);
        self::assertSame('a | b', SecurityHeaderFinding::pass($oneOf, 'a')->expected);
        self::assertSame('a non-empty value', SecurityHeaderFinding::pass($nonEmpty, 'v')->expected);
        self::assertSame('max-age=...', SecurityHeaderFinding::pass($maxAge, 'max-age=1')->expected);
        self::assertSame('a valid value', SecurityHeaderFinding::pass($unknown, 'v')->expected);
    }

    public function testAnHttpsOnlyCheckIsAuditedOverHttps(): void
    {
        $response = new Response(200, ['Strict-Transport-Security' => 'max-age=1']);

        $audit = SecurityHeaderAudit::over($response, 'https');

        self::assertSame(
            SecurityHeaderFinding::VERDICT_PASS,
            $audit->findings[7]->verdict,
        );
    }

    public function testAnHttpsOnlyCheckMissingOverHttpsIsAMissingFinding(): void
    {
        $audit = SecurityHeaderAudit::over(new Response(200), 'https');

        self::assertSame(
            SecurityHeaderFinding::VERDICT_MISSING,
            $audit->findings[7]->verdict,
        );
    }

    public function testAnAdvisoryFindingNeverFailsTheAudit(): void
    {
        $response = new Response(200, [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Content-Security-Policy' => "default-src 'self'",
            'Permissions-Policy' => 'camera=()',
            'Cross-Origin-Embedder-Policy' => '',
        ]);

        $audit = SecurityHeaderAudit::over($response, 'http');

        self::assertTrue($audit->passed());

        $embedder = $audit->findings[9];

        self::assertSame(SecurityHeaderFinding::VERDICT_INVALID, $embedder->verdict);
        self::assertFalse($embedder->required);
        self::assertTrue($embedder->isFinding());
    }

    public function testAnEmptyValuedFieldIsInvalidRatherThanMissing(): void
    {
        $response = new Response(200, ['Content-Security-Policy' => '']);

        $audit = SecurityHeaderAudit::over($response, 'http');

        self::assertSame(
            SecurityHeaderFinding::VERDICT_INVALID,
            $audit->findings[6]->verdict,
        );
    }

    public function testAHeaderFieldIsReadCaseInsensitively(): void
    {
        $response = new Response(200, ['x-content-type-options' => 'nosniff']);

        $audit = SecurityHeaderAudit::over($response, 'http');

        self::assertSame(
            SecurityHeaderFinding::VERDICT_PASS,
            $audit->findings[0]->verdict,
        );
    }

    public function testADuplicatedFieldAnswersItsJoinedValueAndFailsTheGrammar(): void
    {
        $response = new Response(200, ['X-Content-Type-Options' => ['nosniff', 'nosniff']]);

        $audit = SecurityHeaderAudit::over($response, 'http');

        self::assertSame(
            SecurityHeaderFinding::VERDICT_INVALID,
            $audit->findings[0]->verdict,
        );
        self::assertSame('nosniff, nosniff', $audit->findings[0]->actual);
    }

    public function testTheFindingCarriesTheCheckTierAndItsGuidanceNote(): void
    {
        $audit = SecurityHeaderAudit::over(new Response(200), 'http');

        $missing = $audit->findings[0];

        self::assertTrue($missing->required);
        self::assertSame(
            'the response must refuse content-type sniffing outright',
            $missing->note,
        );
        self::assertSame('', $missing->actual);
    }
}
