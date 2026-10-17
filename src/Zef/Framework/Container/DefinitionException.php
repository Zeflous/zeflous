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
 *
 * The class is the declaration-defect family's open root: the lanes that
 * refine declarations raise their own subtype (the autowiring lane's
 * {@see Autowiring\AutowiringException}) so a
 * caller catching this class catches every way a declaration can be
 * refused, while a caller needing the lane's specifics narrows to the
 * subtype.
 */
class DefinitionException extends ContainerException
{
}
