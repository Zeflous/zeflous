<?php

declare(strict_types=1);

namespace Zef\Test\Support\Http;

/**
 * The APCu extension boundary's function doubles, owned by one support
 * class for the whole suite: the doubles themselves live in their own
 * real file ({@see apcu_function_doubles.php}), declared in the
 * boundary's own namespace so PHP resolves the boundary's unqualified
 * calls to them first -- in every environment, extension present or
 * not.
 *
 * Every test that exercises the apcu accounting path (the store
 * boundary's own suite, the construction seam's) shares this one
 * definition, so the answers are order-independent by construction
 * rather than by discovery luck: two suites defining the same
 * namespaced functions with different bodies would answer whichever
 * definition the file discovery happened to run first. The file is
 * required once per process (require_once semantics), and this class's
 * define() resets the typed state ({@see ApcuDoubleState}) the doubles
 * answer from, so each test starts from the same baseline.
 *
 * @internal
 */
final class ApcuFunctionDoubles
{
    /**
     * Resets the typed state the doubles answer from and makes sure
     * their one definition is loaded in this process.
     */
    public static function define(): void
    {
        ApcuDoubleState::$incCalls = [];
        ApcuDoubleState::$addCalls = [];
        ApcuDoubleState::$fetchCalls = [];
        ApcuDoubleState::$counters = [];
        ApcuDoubleState::$entries = [];
        ApcuDoubleState::$incAnswer = null;
        ApcuDoubleState::$fetchAnswer = null;

        require_once __DIR__ . '/apcu_function_doubles.php';
    }
}
