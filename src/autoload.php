<?php

/**
 * ZEF Framework bootstrap.
 *
 * This file is registered in `composer.json` under `autoload.files`, which means
 * Composer includes it exactly once per request, before any framework class is
 * resolved. Keep it small, deterministic and free of I/O.
 *
 * Its job is to publish the framework root path so that runtime code (config
 * loading, template resolution, RoadRunner worker bootstrapping) can locate the
 * installation without guessing.
 */

declare(strict_types=1);

if (!defined('ZEF_FRAMEWORK_ROOT')) {
    define('ZEF_FRAMEWORK_ROOT', dirname(__DIR__));
}
