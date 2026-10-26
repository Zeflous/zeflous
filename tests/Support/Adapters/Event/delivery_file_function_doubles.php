<?php

/**
 * The file delivery boundary's function doubles, loaded once into the
 * adapter's own namespace for the whole suite: the file fold calls
 * file_put_contents, chmod, rename, unlink, mkdir, scandir and
 * file_get_contents unqualified, so PHP resolves them in the namespace
 * first -- these doubles shadow the global calls, answer the engine's
 * own result by default, and expose the failure arms the tests arm
 * explicitly (a failed write, an unremovable entry, an unscannable
 * root, an unreadable record), without the PHP warnings the engine's
 * own failure answers would otherwise emit.
 *
 * This is a real, analysed file rather than an eval'd string, for the
 * same reasons the replay snapshot boundary's doubles are (see
 * {@see tests/Support/Http/replay_snapshot_function_doubles.php}): the
 * state they answer from is the typed support class
 * {@see \Zef\Test\Support\Adapters\Event\DeliveryFileDoubleState}.
 *
 * Passthrough is the default for every double, so every other suite
 * keeps its real behaviour; only a test that arms a failure sees a
 * double's own answer.
 *
 * @internal
 */

declare(strict_types=1);

namespace Zef\Framework\Adapters\Event;

use Zef\Test\Support\Adapters\Event\DeliveryFileDoubleState;

use function chmod as engine_chmod;
use function file_get_contents as engine_file_get_contents;
use function file_put_contents as engine_file_put_contents;
use function mkdir as engine_mkdir;
use function rename as engine_rename;
use function scandir as engine_scandir;
use function unlink as engine_unlink;

/**
 * @param resource|string $data    the payload the engine's own call accepts
 * @param null|resource   $context the stream context the engine's own call accepts
 */
function file_put_contents(string $filename, $data, int $flags = 0, $context = null): false|int
{
    if (DeliveryFileDoubleState::$filePutContentsAnswer !== null) {
        return DeliveryFileDoubleState::$filePutContentsAnswer;
    }

    return engine_file_put_contents($filename, $data, $flags, $context);
}

function chmod(string $filename, int $permissions): bool
{
    if (DeliveryFileDoubleState::$chmodAnswer !== null) {
        return DeliveryFileDoubleState::$chmodAnswer;
    }

    return engine_chmod($filename, $permissions);
}

function rename(string $from, string $to): bool
{
    if (DeliveryFileDoubleState::$renameAnswers !== []) {
        return array_shift(DeliveryFileDoubleState::$renameAnswers);
    }

    return engine_rename($from, $to);
}

function unlink(string $filename): bool
{
    if (DeliveryFileDoubleState::$unlinkAnswer !== null) {
        return DeliveryFileDoubleState::$unlinkAnswer;
    }

    return engine_unlink($filename);
}

function mkdir(string $directory, int $permissions = 0o777, bool $recursive = false): bool
{
    if (DeliveryFileDoubleState::$mkdirAnswer !== null) {
        return DeliveryFileDoubleState::$mkdirAnswer;
    }

    return engine_mkdir($directory, $permissions, $recursive);
}

/**
 * @param 0|1|2 $sorting_order
 *
 * @return false|list<string>
 */
function scandir(string $directory, int $sorting_order = \SCANDIR_SORT_ASCENDING): array|false
{
    if (DeliveryFileDoubleState::$scandirAnswer !== null) {
        return DeliveryFileDoubleState::$scandirAnswer;
    }

    return engine_scandir($directory, $sorting_order);
}

function file_get_contents(string $filename): false|string
{
    if (DeliveryFileDoubleState::$fileGetContentsAnswer !== null) {
        return DeliveryFileDoubleState::$fileGetContentsAnswer;
    }

    return engine_file_get_contents($filename);
}
