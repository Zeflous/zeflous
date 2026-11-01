<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

use Zef\Framework\Exception\ZefException;

/**
 * Thrown when the module engine refuses a declaration it cannot boot honestly
 * (fail closed).
 *
 * Every ambiguity the engine meets -- a malformed module name or version, a
 * manifest that misses a field or carries an unknown one, a module class that
 * does not exist or does not implement the contract, a constructor that
 * cannot answer, a dependency that is not declared or that closes a cycle,
 * two modules claiming one service identifier, a module claiming a reserved
 * framework identifier, a configuration block that arrives without the
 * schema that validates it, or a block that fails its schema -- is a
 * boot-time failure raised through this class. The engine never silently
 * skips a module, reorders around a defect, or lets a second claimant
 * overwrite the first.
 */
final class ModuleException extends ZefException
{
}
