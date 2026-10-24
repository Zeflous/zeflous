<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\SessionState;

/**
 * The request-scoped session bag, on its own terms: the flags the
 * outbound boundary reads (freshly minted, dirtied by writes, marked
 * for destruction), the write-honest dirty flag (a removal over an
 * absent key is the no-op the storage is never asked to persist),
 * and the copy semantics that keep the raw bag's exposure from
 * dirtying anything.
 *
 * @internal
 */
#[CoversClass(SessionState::class)]
final class SessionStateTest extends TestCase
{
    public function testAFreshlyMintedSessionCarriesItsFlags(): void
    {
        $sessionState = new SessionState('AQ', [], isNew: true);

        self::assertSame('AQ', $sessionState->id());
        self::assertTrue($sessionState->isNew());
        self::assertFalse($sessionState->isDirty(), 'a minted session has no writes the storage has not seen');
        self::assertFalse($sessionState->isDestroyed());
        self::assertSame([], $sessionState->data());
    }

    public function testALoadedSessionCarriesTheBagItWasLoadedWith(): void
    {
        $sessionState = new SessionState('AQ', ['user' => 7, 'cart' => ['coffee']]);

        self::assertFalse($sessionState->isNew());
        self::assertSame(7, $sessionState->value('user'));
        self::assertSame(['coffee'], $sessionState->value('cart'));
    }

    public function testAnAbsentKeyAnswersNull(): void
    {
        $sessionState = new SessionState('AQ', ['user' => 7]);

        self::assertNull($sessionState->value('lang'));
    }

    public function testAWriteMarksTheBagDirty(): void
    {
        $sessionState = new SessionState('AQ', []);

        $sessionState->set('user', 7);

        self::assertTrue($sessionState->isDirty());
        self::assertSame(7, $sessionState->value('user'));
    }

    public function testARemovalOfACarriedKeyMarksTheBagDirty(): void
    {
        $sessionState = new SessionState('AQ', ['user' => 7]);

        $sessionState->remove('user');

        self::assertTrue($sessionState->isDirty());
        self::assertNull($sessionState->value('user'));
    }

    public function testARemovalOfAnAbsentKeyIsTheNoOpItSpells(): void
    {
        $sessionState = new SessionState('AQ', ['user' => 7]);

        $sessionState->remove('lang');

        self::assertFalse(
            $sessionState->isDirty(),
            'a removal over an absent key never dirties the bag, so the storage is never asked to persist a no-op',
        );
        self::assertSame(['user' => 7], $sessionState->data());
    }

    public function testDestructionEmptiesTheBagAndMarksIt(): void
    {
        $sessionState = new SessionState('AQ', ['user' => 7]);

        $sessionState->destroy();

        self::assertTrue($sessionState->isDestroyed());
        self::assertSame([], $sessionState->data());
    }

    public function testTheRawBagsExposureIsCopiedOnReturn(): void
    {
        $sessionState = new SessionState('AQ', ['user' => 7]);

        $data = $sessionState->data();
        $data['user'] = 42;
        $data['lang'] = 'id';

        self::assertSame(
            ['user' => 42, 'lang' => 'id'],
            $data,
            'the caller mutated its own copy',
        );
        self::assertSame(
            ['user' => 7],
            $sessionState->data(),
            "mutating the array the read answered mutates the caller's copy, never the session's",
        );
        self::assertFalse($sessionState->isDirty());
    }

    public function testAWriteAfterADestructionStillAnswersTheDestruction(): void
    {
        $sessionState = new SessionState('AQ', ['user' => 7]);

        $sessionState->destroy();
        $sessionState->set('lang', 'id');

        self::assertTrue($sessionState->isDestroyed(), 'the destruction verdict the boundary reads is final');
        self::assertSame(['lang' => 'id'], $sessionState->data(), 'the bag still answers what was written into it');
    }
}
