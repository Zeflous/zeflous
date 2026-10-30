<?php

declare(strict_types=1);

namespace Zef\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\EventDeliveryDeclaration;
use Zef\Framework\FrameworkDefaults;

/**
 * Pins the events family's declaration fold: the strict census of
 * the one `events.deferred` knob block (the silent posture an
 * absent block answers, the named halts a typo'd or wrong-typed
 * knob earns, the closed store-word vocabulary, the capacity's
 * integer seam with its environment-overlay fold), and the drift
 * pin that holds the framework profile's own declaration and the
 * shipped configuration file to the same knobs -- the two cannot
 * disagree without a test naming it. The covers list names both
 * classes the drift pin exercises (the declaration's own default
 * constant and the Default layer's block): the suite runs with
 * requireCoverageMetadata, so a pin whose coverage of
 * FrameworkDefaults is not named would be invisible to the
 * mutation ledger -- the covers-attribution doctrine KDD-DU13
 * spelled first.
 *
 * @internal
 */
#[CoversClass(EventDeliveryDeclaration::class)]
#[CoversClass(FrameworkDefaults::class)]
final class EventDeliveryDeclarationTest extends TestCase
{
    public function testAnAbsentEventsBlockAnswersTheSilentPosture(): void
    {
        $declaration = EventDeliveryDeclaration::fromConfig(new Config([]));

        self::assertSame('', $declaration->store);
        self::assertSame('', $declaration->path);
        self::assertSame(1024, $declaration->capacity);
        self::assertFalse($declaration->wantsWiredStore(), 'an absent family declares no wired default');
    }

    public function testAnAbsentDeferredMapAnswersTheSilentPosture(): void
    {
        $declaration = EventDeliveryDeclaration::fromConfig(new Config([
            'events' => [],
        ]));

        self::assertSame('', $declaration->store);
        self::assertSame('', $declaration->path);
        self::assertSame(1024, $declaration->capacity);
    }

    public function testTheFileWordIsReadWithItsOwnKnobsVerbatim(): void
    {
        $declaration = EventDeliveryDeclaration::fromConfig(new Config([
            'events' => [
                'deferred' => [
                    'store' => 'file',
                    'path' => '/srv/app/elsewhere',
                    'capacity' => 5,
                ],
            ],
        ]));

        self::assertSame('file', $declaration->store);
        self::assertSame('/srv/app/elsewhere', $declaration->path);
        self::assertSame(5, $declaration->capacity);
        self::assertTrue($declaration->wantsWiredStore(), 'the non-empty word asks for the wired default');
    }

    public function testTheEnvironmentsSpellingFoldsToTheFileWord(): void
    {
        $declaration = EventDeliveryDeclaration::fromConfig(new Config([
            'events' => [
                'deferred' => [
                    'store' => 'FILE',
                ],
            ],
        ]));

        self::assertSame('file', $declaration->store);
        self::assertTrue($declaration->wantsWiredStore());
    }

    public function testTheShippedProfileAndTheFrameworkDefaultsDeclareTheSameKnobs(): void
    {
        $profile = require ZEF_FRAMEWORK_ROOT . '/app/configs/events.php';

        self::assertSame(
            FrameworkDefaults::profile()['events'],
            $profile,
            'the Default layer and the shipped file cannot drift apart',
        );
    }

    public function testANonMapEventsConfigurationRefuses(): void
    {
        try {
            EventDeliveryDeclaration::fromConfig(new Config([
                'events' => 'the-calendar',
            ]));
            self::fail('A non-map events configuration must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The events configuration must be a map, string given.',
                $configException->getMessage(),
            );
        }
    }

    public function testAnUnknownEventsKnobRefusesWithTheKnownListSpelled(): void
    {
        try {
            EventDeliveryDeclaration::fromConfig(new Config([
                'events' => [
                    'deffered' => [],
                ],
            ]));
            self::fail("A typo'd events knob must refuse, never fall through silently.");
        } catch (ConfigException $configException) {
            self::assertSame(
                "The events configuration carries the unknown knob 'deffered' (known knobs: deferred).",
                $configException->getMessage(),
            );
        }
    }

    public function testANonMapDeferredMapRefuses(): void
    {
        try {
            EventDeliveryDeclaration::fromConfig(new Config([
                'events' => [
                    'deferred' => 'later',
                ],
            ]));
            self::fail('A non-map events.deferred configuration must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The events.deferred configuration must be a map, string given.',
                $configException->getMessage(),
            );
        }
    }

    public function testAnUnknownDeferredKnobRefusesWithTheKnownListSpelled(): void
    {
        try {
            EventDeliveryDeclaration::fromConfig(new Config([
                'events' => [
                    'deferred' => [
                        'capcity' => 2048,
                    ],
                ],
            ]));
            self::fail("A typo'd events.deferred knob must refuse, never fall through silently.");
        } catch (ConfigException $configException) {
            self::assertSame(
                "The events.deferred configuration carries the unknown knob 'capcity'"
                . ' (known knobs: store, path, capacity).',
                $configException->getMessage(),
            );
        }
    }

    public function testAWrongTypedStoreWordRefuses(): void
    {
        try {
            EventDeliveryDeclaration::fromConfig(new Config([
                'events' => ['deferred' => ['store' => 42]],
            ]));
            self::fail('A wrong-typed store word must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'events.deferred.store must be a string.',
                $configException->getMessage(),
            );
        }
    }

    public function testAnUnknownStoreWordRefusesWithTheVocabularySpelled(): void
    {
        try {
            EventDeliveryDeclaration::fromConfig(new Config([
                'events' => ['deferred' => ['store' => 'redis']],
            ]));
            self::fail('A store word outside the closed vocabulary must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                "The events.deferred.store word 'redis' is not one the wiring knows"
                . ' (known words: file).',
                $configException->getMessage(),
            );
        }
    }

    public function testAWrongTypedPathRefuses(): void
    {
        try {
            EventDeliveryDeclaration::fromConfig(new Config([
                'events' => ['deferred' => ['path' => 404]],
            ]));
            self::fail('A wrong-typed path must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'events.deferred.path must be a string.',
                $configException->getMessage(),
            );
        }
    }

    public function testTheCapacityReadsItsNativeInteger(): void
    {
        $declaration = EventDeliveryDeclaration::fromConfig(new Config([
            'events' => ['deferred' => ['capacity' => 2048]],
        ]));

        self::assertSame(2048, $declaration->capacity);
    }

    public function testTheCapacityFoldsTheEnvironmentOverlaysAllDigitString(): void
    {
        $declaration = EventDeliveryDeclaration::fromConfig(new Config([
            'events' => ['deferred' => ['capacity' => '4096']],
        ]));

        self::assertSame(4096, $declaration->capacity);
    }

    public function testAWrongTypedCapacityRefuses(): void
    {
        try {
            EventDeliveryDeclaration::fromConfig(new Config([
                'events' => ['deferred' => ['capacity' => 1.5]],
            ]));
            self::fail('A wrong-typed capacity must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The events.deferred.capacity knob must be an integer or an'
                . ' all-digit string, float given.',
                $configException->getMessage(),
            );
        }
    }

    public function testAZeroCapacityRefuses(): void
    {
        try {
            EventDeliveryDeclaration::fromConfig(new Config([
                'events' => ['deferred' => ['capacity' => 0]],
            ]));
            self::fail('A zero capacity must refuse.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The events.deferred.capacity knob must be at least one, got 0'
                . ' -- a capacity of zero would refuse every admission the'
                . ' wired store is asked to hold.',
                $configException->getMessage(),
            );
        }
    }

    public function testTheBoundaryCapacityOfOneReads(): void
    {
        $declaration = EventDeliveryDeclaration::fromConfig(new Config([
            'events' => ['deferred' => ['capacity' => 1]],
        ]));

        self::assertSame(1, $declaration->capacity);
    }
}
