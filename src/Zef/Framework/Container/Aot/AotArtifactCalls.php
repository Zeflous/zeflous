<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use Zef\Framework\Container\MethodCall;

/**
 * The artifact's method-call walk: the build-kind entry's `calls`
 * member validated into the typed value objects the install half's
 * reconstruction consumes -- its own unit the same way the dump side's
 * calls grammar is, so the payload's call shape has a single spelling.
 *
 * Each call is exactly a method and an argument list: the method a
 * non-empty string, the arguments a list -- the members the dump wrote
 * from the declaration the pipeline planned.
 */
final readonly class AotArtifactCalls
{
    /**
     * Validates one build-kind entry's calls member into its value
     * objects, in declaration order.
     *
     * @param mixed $calls the entry's `calls` member
     *
     * @return list<MethodCall>
     */
    public static function validated(int|string $id, mixed $calls): array
    {
        if (!\is_array($calls)) {
            throw AotRefusals::entryMalformed($id);
        }

        $methodCalls = [];

        foreach ($calls as $call) {
            if (!\is_array($call) || array_keys($call) !== ['method', 'arguments']) {
                throw AotRefusals::entryMalformed($id);
            }

            $methodCalls[] = new MethodCall(
                AotMembers::string($id, $call, 'method'),
                AotMembers::list($id, $call, 'arguments'),
            );
        }

        return $methodCalls;
    }
}
