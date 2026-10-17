<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionEnum;
use ValueError;
use Zef\Framework\Http\AuthStatus;

/**
 * The authentication resolution's whole status vocabulary, pinned as its own
 * unit: the five backing spellings the provider contract serialises, read
 * back through the dynamic case list (the static analyser already knows the
 * literal pairings; a test repeating them literally would assert nothing).
 *
 * @internal
 */
#[CoversClass(AuthStatus::class)]
final class AuthStatusTest extends TestCase
{
    public function testTheVocabularyIsTheFiveDocumentedSpellings(): void
    {
        // Built through reflection -- the analyser folds a loop over the
        // static case list into the very map under test, and a pin it can
        // prove would assert nothing. Reflection is opaque to it, so the
        // vocabulary is genuinely read back from the declared enum.
        $spellings = [];

        foreach (new ReflectionEnum(AuthStatus::class)->getCases() as $reflectionEnumBackedCase) {
            $spellings[$reflectionEnumBackedCase->getName()] = $reflectionEnumBackedCase->getBackingValue();
        }

        self::assertSame([
            'UNAUTHENTICATED' => 'unauthenticated',
            'AUTHENTICATED' => 'authenticated',
            'FAILED' => 'failed',
            'UNAVAILABLE' => 'unavailable',
            'EXPIRED' => 'expired',
        ], $spellings);
    }

    public function testEveryCaseRoundTripsThroughItsBackingValue(): void
    {
        foreach (AuthStatus::cases() as $status) {
            self::assertSame($status, AuthStatus::from($status->value), $status->name);
        }
    }

    public function testAnUnknownSpellingIsRefused(): void
    {
        $this->expectException(ValueError::class);

        AuthStatus::from('authenticated-ish');
    }
}
