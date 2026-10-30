<?php

/**
 * The session file store's function doubles, loaded once into the
 * adapter's own namespace for the whole suite: the staging fold
 * calls file_put_contents, chmod and rename unqualified, the
 * layout fold calls scandir, file_exists and unlink, the sweep and
 * the load read through file_get_contents, and the directory fold
 * calls realpath and fileperms -- so PHP resolves them in the
 * namespace first. These doubles shadow the global calls, answer
 * the engine's own result by default, and expose the failure arms
 * the tests arm explicitly (a refused write, an unsecurable
 * record, an unfinalisable record, an unremovable record, an
 * unscannable root, an unreadable record, an unresolvable
 * directory, unreadable permissions), without the PHP warnings the
 * engine's own failure answers would otherwise emit.
 *
 * The rename double's armed answers carry one rule the delivery
 * boundary's own doubles spelled first (see {@see
 * tests/Support/Adapters/Event/delivery_file_function_doubles.php}):
 * `null` is the ENGINE-PASSTHROUGH arm (the rename really happens,
 * so a successfully finalised record is real on disk), while `true`
 * and `false` answer WITHOUT the engine call. The file_exists
 * double carries the same sequence rule, answering the
 * concurrent-retirement window the remove fold's re-check owns: an
 * armed `[null, false]` spells a record that existed at the check
 * and was retired by a sibling worker before the unlink.
 *
 * This is a real, analysed file rather than an eval'd string, for
 * the same reasons the replay snapshot boundary's doubles are: the
 * state they answer from is the typed support class {@see
 * \Zef\Test\Support\Adapters\Session\SessionFileDoubleState}.
 *
 * Passthrough is the default for every double, so every other
 * suite keeps its real behaviour; only a test that arms a failure
 * sees a double's own answer.
 *
 * @internal
 */

declare(strict_types=1);

namespace Zef\Framework\Adapters\Session;

use Zef\Test\Support\Adapters\Session\SessionFileDoubleState;

use function chmod as engine_chmod;
use function file_exists as engine_file_exists;
use function file_get_contents as engine_file_get_contents;
use function file_put_contents as engine_file_put_contents;
use function fileperms as engine_fileperms;
use function realpath as engine_realpath;
use function rename as engine_rename;
use function scandir as engine_scandir;
use function unlink as engine_unlink;

/**
 * @param resource|string $data    the payload the engine's own call accepts
 * @param null|resource   $context the stream context the engine's own call accepts
 */
function file_put_contents(string $filename, $data, int $flags = 0, $context = null): false|int
{
    if (SessionFileDoubleState::$filePutContentsAnswer !== null) {
        return SessionFileDoubleState::$filePutContentsAnswer;
    }

    return engine_file_put_contents($filename, $data, $flags, $context);
}

function chmod(string $filename, int $permissions): bool
{
    if (SessionFileDoubleState::$chmodAnswer !== null) {
        return SessionFileDoubleState::$chmodAnswer;
    }

    return engine_chmod($filename, $permissions);
}

function rename(string $from, string $to): bool
{
    if (SessionFileDoubleState::$renameAnswers !== []) {
        $answer = array_shift(SessionFileDoubleState::$renameAnswers);

        if ($answer !== null) {
            return $answer;
        }
    }

    return engine_rename($from, $to);
}

function unlink(string $filename): bool
{
    if (SessionFileDoubleState::$unlinkAnswer !== null) {
        return SessionFileDoubleState::$unlinkAnswer;
    }

    return engine_unlink($filename);
}

/**
 * @param 0|1 $sorting_order
 *
 * @return false|list<string>
 */
function scandir(string $directory, int $sorting_order = \SCANDIR_SORT_ASCENDING): array|false
{
    if (SessionFileDoubleState::$scandirAnswer !== null) {
        return SessionFileDoubleState::$scandirAnswer;
    }

    return engine_scandir($directory, $sorting_order);
}

function file_get_contents(string $filename): false|string
{
    if (SessionFileDoubleState::$fileGetContentsAnswer !== null) {
        return SessionFileDoubleState::$fileGetContentsAnswer;
    }

    return engine_file_get_contents($filename);
}

/**
 * The store directory's own resolution: the engine answers false
 * only for a path it cannot resolve, so the armed answer is the
 * fail-closed refusal arm the geometry's own test names.
 */
function realpath(string $path): false|string
{
    if (SessionFileDoubleState::$realpathAnswer !== null) {
        return SessionFileDoubleState::$realpathAnswer;
    }

    return engine_realpath($path);
}

/**
 * The store directory's own permission reading: the engine answers
 * false only for a path it cannot stat, so the armed answer is the
 * fail-closed refusal arm the posture's own test names.
 */
function fileperms(string $filename): false|int
{
    if (SessionFileDoubleState::$filepermsAnswer !== null) {
        return SessionFileDoubleState::$filepermsAnswer;
    }

    return engine_fileperms($filename);
}

function file_exists(string $filename): bool
{
    if (SessionFileDoubleState::$fileExistsAnswers !== []) {
        $answer = array_shift(SessionFileDoubleState::$fileExistsAnswers);

        if ($answer !== null) {
            return $answer;
        }
    }

    return engine_file_exists($filename);
}
