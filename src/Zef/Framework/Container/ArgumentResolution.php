<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The resolution half of a definition's argument list (see
 * {@see DefinitionArguments} for the validation half).
 *
 * References become the container's answers; plain values pass through
 * verbatim. The reference form is recognised by its grammar shape -- a map
 * whose single 'service' key holds the identifier -- and the identifier is
 * re-narrowed to a string here, so an unvalidated hand-built definition
 * cannot smuggle a non-string identifier into {@see Container::get()}.
 */
final readonly class ArgumentResolution
{
    /**
     * @param list<mixed> $arguments
     *
     * @return list<mixed>
     */
    public static function resolve(array $arguments, Container $container): array
    {
        return array_map(
            static fn (mixed $argument): mixed => self::resolvedEntry($argument, $container),
            $arguments,
        );
    }

    /**
     * The resolution of one argument entry: a well-formed reference answers
     * the container's service, anything else is the plain value verbatim.
     */
    private static function resolvedEntry(mixed $argument, Container $container): mixed
    {
        if (\is_array($argument) && \array_key_exists('service', $argument) && \is_string($argument['service'])) {
            return $container->get($argument['service']);
        }

        return $argument;
    }
}
