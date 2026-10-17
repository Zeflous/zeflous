<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionEnum;
use ValueError;
use Zef\Framework\Http\AuthVerdict;

/**
 * The authorization verdict's vocabulary, pinned as its own unit: the closed
 * allow/deny pair the admission decision composes -- a third spelling would
 * move the tie-break somewhere the caller cannot see it. The spellings are
 * read back through the dynamic case list; a test repeating them literally
 * would assert nothing the static analyser does not already know.
 *
 * @internal
 */
#[CoversClass(AuthVerdict::class)]
final class AuthVerdictTest extends TestCase
{
    public function testTheVocabularyIsTheTwoDocumentedSpellings(): void
    {
        // Built through reflection for the same reason as the status
        // vocabulary: a map the analyser can fold is a pin that asserts
        // nothing.
        $spellings = [];

        foreach (new ReflectionEnum(AuthVerdict::class)->getCases() as $reflectionEnumBackedCase) {
            $spellings[$reflectionEnumBackedCase->getName()] = $reflectionEnumBackedCase->getBackingValue();
        }

        self::assertSame([
            'ALLOW' => 'allow',
            'DENY' => 'deny',
        ], $spellings);
    }

    public function testEveryCaseRoundTripsThroughItsBackingValue(): void
    {
        foreach (AuthVerdict::cases() as $verdict) {
            self::assertSame($verdict, AuthVerdict::from($verdict->value), $verdict->name);
        }
    }

    public function testAnUnknownSpellingIsRefused(): void
    {
        $this->expectException(ValueError::class);

        AuthVerdict::from('abstain');
    }
}
