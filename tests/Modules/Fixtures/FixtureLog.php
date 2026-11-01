<?php

declare(strict_types=1);

namespace Zef\Test\Modules\Fixtures;

/**
 * The fixture modules' shared call log: the two-phase boot's own evidence
 * tape -- which module ran which phase, in which order.
 */
final class FixtureLog
{
    /** @var list<string> */
    public static array $events = [];

    public static function record(string $phase, string $name): void
    {
        self::$events[] = "{$phase}:{$name}";
    }

    /**
     * @return list<string>
     */
    public static function events(): array
    {
        return self::$events;
    }

    public static function reset(): void
    {
        self::$events = [];
    }
}
