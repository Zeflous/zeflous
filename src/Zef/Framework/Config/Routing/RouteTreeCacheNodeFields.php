<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Zef\Framework\Config\ConfigException;

/**
 * The node-format read side of the compiled route tree cache
 * ({@see RouteTreeCache}): one question -- is this exported value one of
 * the cache's compiled nodes?
 *
 * An exported node is an array of exactly the four node fields, with a
 * string prefix, list static children, a parameter edge that is either a
 * node or absent, and a list of route references. Anything else -- a
 * non-array, an unknown or missing field, a wrong shape for any field --
 * is corruption the compiled format never contains, and fails closed
 * with the artifact path in the message. The root node and every
 * parameter node carry an empty prefix; a static child never does (its
 * first character keys it under its parent -- the rehydration's own
 * guard, in {@see RouteTreeCacheHydration}).
 *
 * @internal
 */
final readonly class RouteTreeCacheNodeFields
{
    private const array NODE_FIELDS = [
        'prefix' => true,
        'static' => true,
        'parameter' => true,
        'routes' => true,
    ];

    /**
     * @param non-empty-string $file path of the cache artifact
     */
    public function __construct(
        private string $file,
    ) {
    }

    /**
     * Narrows one exported node to the cache's node format.
     *
     * @param mixed $node the exported node
     *
     * @return array{
     *     prefix: string,
     *     static: list<mixed>,
     *     parameter: null|array<mixed>,
     *     routes: list<mixed>,
     * }
     *
     * @throws ConfigException when the exported value is not an array of
     *                         exactly the four node fields with the right
     *                         shape for each
     */
    public function narrow(mixed $node): array
    {
        if (!\is_array($node)) {
            throw new ConfigException(
                \sprintf(
                    'The route tree cache "%s" is corrupted: a compiled node is not an array, got %s.',
                    $this->file,
                    get_debug_type($node),
                ),
            );
        }

        foreach (array_keys($node) as $field) {
            if (isset(self::NODE_FIELDS[$field])) {
                continue;
            }

            throw new ConfigException(
                \sprintf(
                    'The route tree cache "%s" is corrupted: a compiled node carries the unknown field "%s".',
                    $this->file,
                    $field,
                ),
            );
        }

        foreach (array_keys(self::NODE_FIELDS) as $field) {
            if (\array_key_exists($field, $node)) {
                continue;
            }

            throw new ConfigException(
                \sprintf(
                    'The route tree cache "%s" is corrupted: a compiled node does not carry the "%s" field.',
                    $this->file,
                    $field,
                ),
            );
        }

        if (!\is_string($node['prefix'])) {
            throw new ConfigException(
                \sprintf(
                    'The route tree cache "%s" is corrupted: a compiled node prefix is not a string.',
                    $this->file,
                ),
            );
        }

        if (!\is_array($node['static']) || !array_is_list($node['static'])) {
            throw new ConfigException(
                \sprintf(
                    'The route tree cache "%s" is corrupted: a compiled node static children are not a list.',
                    $this->file,
                ),
            );
        }

        if ($node['parameter'] !== null && !\is_array($node['parameter'])) {
            throw new ConfigException(
                \sprintf(
                    'The route tree cache "%s" is corrupted: a compiled node parameter edge '
                    . 'is neither a node nor absent.',
                    $this->file,
                ),
            );
        }

        if (!\is_array($node['routes']) || !array_is_list($node['routes'])) {
            throw new ConfigException(
                \sprintf(
                    'The route tree cache "%s" is corrupted: a compiled node route references are not a list.',
                    $this->file,
                ),
            );
        }

        return [
            'prefix' => $node['prefix'],
            'static' => $node['static'],
            'parameter' => $node['parameter'],
            'routes' => $node['routes'],
        ];
    }
}
