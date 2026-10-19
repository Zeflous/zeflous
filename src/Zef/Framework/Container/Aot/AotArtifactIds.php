<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The artifact's identifier-list walk: the one validation both flat
 * members that carry service identifiers share -- the unreferenced
 * report and every tag's identifier list -- consolidated so the list
 * grammar has a single spelling ({@see AotRefusals::artifactShape()}
 * naming what the list is for, the file named in every refusal).
 *
 * A list of non-empty strings, in the order the artifact baked: the
 * unreferenced report's declaration order and a tag's first-declaration
 * order are both part of what the dump promised.
 */
final readonly class AotArtifactIds
{
    /**
     * Validates one identifier list under its own description.
     *
     * @param string $what the member's description, as the refusal spells it
     * @param mixed  $ids  the payload member that must be the list
     *
     * @return list<string>
     */
    public static function validated(string $file, string $what, mixed $ids): array
    {
        if (!\is_array($ids) || !array_is_list($ids)) {
            throw AotRefusals::artifactShape($file, "{$what} must be a list of identifiers");
        }

        $identifiers = [];

        foreach ($ids as $id) {
            if (!\is_string($id) || $id === '') {
                throw AotRefusals::artifactShape($file, "{$what} must be a list of identifiers");
            }

            $identifiers[] = $id;
        }

        return $identifiers;
    }
}
