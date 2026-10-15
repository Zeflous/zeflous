<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\SecurityFamilies;

/**
 * The `security` map's family census, on its own terms: the subtree
 * reading each family's factory goes through, and the fail-closed
 * rejection of an unknown sibling -- the second family's own strictness
 * seam (KDD-SC3's deferred promise, landed here).
 *
 * @internal
 */
#[CoversClass(SecurityFamilies::class)]
final class SecurityFamiliesTest extends TestCase
{
    public function testAnAbsentSecurityMapAnswersTheAbsentFamily(): void
    {
        self::assertNull(SecurityFamilies::subtree(null, 'headers'));
        self::assertNull(SecurityFamilies::subtree(null, 'rate_limit'));
    }

    public function testAFamilylessMapAnswersTheAbsentFamily(): void
    {
        self::assertNull(SecurityFamilies::subtree([], 'rate_limit'));
    }

    public function testEachKnownFamilyReadsItsOwnSubtree(): void
    {
        $security = [
            'headers' => ['hsts' => true],
            'rate_limit' => ['enabled' => true],
        ];

        self::assertSame(['hsts' => true], SecurityFamilies::subtree($security, 'headers'));
        self::assertSame(['enabled' => true], SecurityFamilies::subtree($security, 'rate_limit'));
    }

    public function testAnUnknownFamilyHaltsWithTheKnownListSpelledOut(): void
    {
        try {
            SecurityFamilies::subtree(['rate_limitt' => []], 'rate_limit');
            self::fail('An unknown family must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security configuration carries the unknown family 'rate_limitt'"
                . ' (known families: headers, rate_limit, csrf, auth).',
                $httpException->getMessage(),
            );
        }
    }

    public function testANonStringFamilyKeyHalts(): void
    {
        try {
            SecurityFamilies::subtree([7 => []], 'headers');
            self::fail('A non-string family key must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The security configuration carries the unknown family 'int'"
                . ' (known families: headers, rate_limit, csrf, auth).',
                $httpException->getMessage(),
            );
        }
    }

    public function testANonMapSecurityValueHalts(): void
    {
        try {
            SecurityFamilies::subtree('nope', 'headers');
            self::fail('A non-map security value must halt the reading.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security configuration must be a map, string given.',
                $httpException->getMessage(),
            );
        }
    }
}
