<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `calls` grammar's list-level parsing half (the method-call
 * injection lane, ROADMAP area 4; see {@see MethodCall::fromEntry()}
 * for the entry grammar, {@see MethodCallInvocations} for the runtime
 * half).
 *
 * A declaration's `calls` entry is a positional list of call entries.
 * A class-shaped declaration has its methods proven at parse time (the
 * boot discipline: a definition that survives boot never fails at
 * instantiation through a missing method); a factory-shaped
 * declaration cannot know its product's type at boot, so its methods
 * are proven at first resolution instead.
 */
final readonly class MethodCalls
{
    /**
     * Parses the `calls` entry of a definition map into its call list.
     *
     * @param string                  $id    the definition's service identifier
     * @param null|class-string       $class the class shape's class, when the product's
     *                                       type is known at parse time
     * @param array<array-key, mixed> $entry
     *
     * @return list<MethodCall>
     */
    public static function fromKey(string $id, ?string $class, array $entry): array
    {
        $calls = $entry['calls'] ?? null;
        if ($calls === null) {
            return [];
        }

        if (!\is_array($calls)) {
            throw MethodCallRefusals::notAMap($id, $calls);
        }

        if (!array_is_list($calls)) {
            throw MethodCallRefusals::notAPositionalList($id);
        }

        return array_map(
            static fn (mixed $call): MethodCall => MethodCall::fromEntry($id, $class, $call),
            $calls,
        );
    }
}
