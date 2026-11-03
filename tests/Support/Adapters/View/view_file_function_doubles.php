<?php

/**
 * The view adapter's function doubles, loaded once into the adapter's
 * own namespace for the whole suite: the templates-root fold calls
 * realpath and fileperms unqualified, and the resolution fold calls
 * realpath unqualified -- so PHP resolves them in the namespace
 * first. These doubles shadow the global calls, answer the engine's
 * own result by default, and expose the failure arms the tests arm
 * explicitly (an unresolvable root, unreadable permissions, an
 * unresolvable template) without the PHP warnings the engine's own
 * failure answers would otherwise emit.
 *
 * This is a real, analysed file rather than an eval'd string, for
 * the same reasons the session store's doubles are (see {@see
 * tests/Support/Adapters/Session/session_file_function_doubles.php}):
 * the state they answer from is the typed support class {@see
 * \Zef\Test\Support\Adapters\View\ViewFileDoubleState}.
 *
 * Passthrough is the default for every double, so every other suite
 * keeps its real behaviour; only a test that arms a failure sees a
 * double's own answer.
 *
 * @internal
 */

declare(strict_types=1);

namespace Zef\Framework\Adapters\View;

use Zef\Test\Support\Adapters\View\ViewFileDoubleState;

use function fileperms as engine_fileperms;
use function realpath as engine_realpath;

/**
 * The templates root's and the template file's own resolution: the
 * engine answers false only for a path it cannot resolve, so the
 * armed answer is the fail-closed refusal arm both folds' own tests
 * name.
 */
function realpath(string $path): false|string
{
    if (ViewFileDoubleState::$realpathAnswer !== null) {
        return ViewFileDoubleState::$realpathAnswer;
    }

    return engine_realpath($path);
}

/**
 * The templates root's own permission reading: the engine answers
 * false only for a path it cannot stat, so the armed answer is the
 * fail-closed refusal arm the posture's own test names.
 */
function fileperms(string $filename): false|int
{
    if (ViewFileDoubleState::$filepermsAnswer !== null) {
        return ViewFileDoubleState::$filepermsAnswer;
    }

    return engine_fileperms($filename);
}
