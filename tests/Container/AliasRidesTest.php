<?php

declare(strict_types=1);

namespace Zef\Test\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\AliasRides;

/**
 * The alias ride registry's own battery (#770): the reverse index a
 * `set()` re-registration walks -- which memo slots a rebind must
 * forget beyond the re-registered id itself.
 *
 * The walk's own questions, unit-level: the direct rider, the
 * transitive chain (a rider's rider is exactly as stale as the
 * rider), the diamond, the defensive arms (a self-ride, a cycle --
 * verdicts the boot's own graph validator refuses, so the registry
 * walks them closed rather than trusting its input), the drop a
 * re-registered alias id owes, and the re-record that overwrites a
 * stale mark.
 *
 * @internal
 */
#[CoversClass(AliasRides::class)]
final class AliasRidesTest extends TestCase
{
    public function testARecordingAnswersItsReadSideInDeclarationOrder(): void
    {
        $aliasRides = new AliasRides();

        $aliasRides->record('app.mid', 'app.base');
        $aliasRides->record('app.top', 'app.mid');

        self::assertSame(
            ['app.mid' => 'app.base', 'app.top' => 'app.mid'],
            $aliasRides->rides(),
        );
    }

    public function testAReRegistrationForgetsItsDirectRider(): void
    {
        $aliasRides = new AliasRides();

        $aliasRides->record('app.alias', 'app.target');

        self::assertSame(['app.alias'], $aliasRides->forgottenFor('app.target'));
    }

    public function testAReRegistrationForgetsItsRidersTransitively(): void
    {
        // The chain `a -> b -> c`: rebinding `c` must forget `b` and
        // `a` both -- a rider's rider is exactly as stale as the rider.
        $aliasRides = new AliasRides();

        $aliasRides->record('app.a', 'app.b');
        $aliasRides->record('app.b', 'app.c');

        self::assertSame(['app.b', 'app.a'], $aliasRides->forgottenFor('app.c'));
    }

    public function testAReRegistrationForgetsEveryBranchOfADiamond(): void
    {
        $aliasRides = new AliasRides();

        $aliasRides->record('app.left', 'app.base');
        $aliasRides->record('app.right', 'app.base');
        $aliasRides->record('app.top', 'app.left');

        self::assertSame(['app.left', 'app.right', 'app.top'], $aliasRides->forgottenFor('app.base'));
    }

    public function testAReRegistrationOfAnUnknownIdentifierReachesNobody(): void
    {
        $aliasRides = new AliasRides();

        $aliasRides->record('app.alias', 'app.target');

        self::assertSame([], $aliasRides->forgottenFor('app.unrelated'));
    }

    public function testASelfRideIsWalkedClosed(): void
    {
        // The boot's own graph validator refuses an alias cycle, so
        // this arm is the registry's own defensive verdict -- the
        // walk must answer the riders *beyond* the rebound id, never
        // the rebound id itself, whatever the input carries.
        $aliasRides = new AliasRides();

        $aliasRides->record('app.self', 'app.self');

        self::assertSame([], $aliasRides->forgottenFor('app.self'));
    }

    public function testACycleIsWalkedClosedWithoutRepetition(): void
    {
        // `a -> b` and `b -> a`: rebinding `a` reaches `b` once, and
        // the walk stops at the edge it already walked.
        $aliasRides = new AliasRides();

        $aliasRides->record('app.a', 'app.b');
        $aliasRides->record('app.b', 'app.a');

        self::assertSame(['app.b'], $aliasRides->forgottenFor('app.a'));
    }

    public function testADroppedRideNoLongerAnswersTheOldTarget(): void
    {
        $aliasRides = new AliasRides();

        $aliasRides->record('app.alias', 'app.target');
        $aliasRides->drop('app.alias');

        self::assertSame([], $aliasRides->forgottenFor('app.target'));
        self::assertSame([], $aliasRides->rides());
    }

    public function testADroppedAliasStillReachesItsOwnRiders(): void
    {
        // Re-registering an alias id itself: the id answers its own
        // factory now, but the aliases riding *it* keep their marks --
        // their delegated answer changed, transitively.
        $aliasRides = new AliasRides();

        $aliasRides->record('app.mid', 'app.base');
        $aliasRides->record('app.top', 'app.mid');
        $aliasRides->drop('app.mid');

        self::assertSame(['app.top'], $aliasRides->forgottenFor('app.mid'));
    }

    public function testASeenRiderDoesNotStopItsUnseenSiblings(): void
    {
        // A cycle riding a shared node: `a -> b`, `b -> a`, `c -> b` --
        // rebinding `a` reaches `b`, and `b`'s riders list then holds
        // the seen `a` before the unseen `c`: the walk must skip the
        // seen one and still reach every sibling behind it (the
        // defensive arm's own continue -- a break would strand every
        // rider sitting behind a seen one).
        $aliasRides = new AliasRides();

        $aliasRides->record('app.a', 'app.b');
        $aliasRides->record('app.b', 'app.a');
        $aliasRides->record('app.c', 'app.b');

        self::assertSame(['app.b', 'app.c'], $aliasRides->forgottenFor('app.a'));
    }

    public function testAReRecordingOverwritesTheStaleMark(): void
    {
        $aliasRides = new AliasRides();

        $aliasRides->record('app.alias', 'app.old');
        $aliasRides->record('app.alias', 'app.new');

        self::assertSame(['app.alias'], $aliasRides->forgottenFor('app.new'));
        self::assertSame([], $aliasRides->forgottenFor('app.old'));
    }
}
