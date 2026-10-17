<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\DefinitionException;

/**
 * The autowiring lane's refusal (see {@see AutowireCompilerPass}).
 *
 * Every failure the constructor planner surfaces -- an unresolvable
 * parameter, an ambiguous attribute combination, a circular birth chain,
 * an arity mismatch against the reflection-read constructor -- is a
 * defect in the declaration, so it fails closed at boot with the owning
 * service and parameter in the message, never a lazy `ReflectionException` at the
 * first resolution (the same discipline the definitions graph applies to
 * reference failures: declared defects are boot-time failures). Being a
 * declaration defect, it rides the {@see DefinitionException} family the
 * bootstrapper's callers already catch.
 */
final class AutowiringException extends DefinitionException
{
}
