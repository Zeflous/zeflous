<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Contracts\ContainerException;

/**
 * Thrown when a declarative service definition is rejected (fail closed).
 *
 * The configuration-driven definition format ({@see ServiceDefinition})
 * parses plain arrays into typed definitions. Every ambiguity is a hard
 * failure raised through this class at boot time -- never a silent
 * coercion into a working-but-wrong registration.
 */
final class DefinitionException extends ContainerException
{
}
