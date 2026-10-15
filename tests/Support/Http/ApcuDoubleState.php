<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * The APCu function doubles' state, typed: what the eval'd predecessors
 * kept in `$GLOBALS` (the call records, the counted entries, the
 * configurable corrupt answers) lives in static properties the doubles
 * ({@see apcu_function_doubles.php}) answer from and the tests seed and
 * read -- every key the same typed shape, nothing mixed.
 *
 * @internal
 */
final class ApcuDoubleState
{
    /** @var list<array{string, int, int}> */
    public static array $incCalls = [];

    /** @var list<array{string, mixed, int}> */
    public static array $addCalls = [];

    /** @var list<array{string}> */
    public static array $fetchCalls = [];

    /** @var array<string, int> */
    public static array $counters = [];

    /** @var array<string, mixed> */
    public static array $entries = [];

    public static int|string|null $incAnswer = null;

    public static int|string|null $fetchAnswer = null;
}
