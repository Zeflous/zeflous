<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use ParseError;
use Zef\Framework\Config\ConfigException;

/**
 * The file-level read side of the compiled route tree cache
 * ({@see RouteTreeCache}): one question -- does the artifact file carry
 * the cache's format, and what does it carry?
 *
 * Every corruption shape the file can take fails closed with the path in
 * the message, including the torn-write shape a partial
 * `file_put_contents()` actually produces (a `require` that raises a
 * `ParseError`). The narrowing stops at the artifact's own three fields;
 * the node graph's format is {@see RouteTreeCacheNodeFields}'s question,
 * and the replay of that graph is {@see RouteTreeCacheHydration}'s.
 *
 * @internal
 */
final readonly class RouteTreeCacheArtifact
{
    private const array ARTIFACT_FIELDS = [
        'signature' => true,
        'routes' => true,
        'root' => true,
    ];

    /**
     * @param non-empty-string $file path of the cache artifact
     */
    public function __construct(
        private string $file,
    ) {
    }

    /**
     * Reads the artifact and narrows it to the cache format: exactly the
     * three artifact fields, a non-empty recorded signature, and a route
     * table that is a list. The root passes through as it is -- its own
     * format is narrowed later, node by node.
     *
     * @return array{signature: non-empty-string, routes: list<mixed>, root: mixed}
     *
     * @throws ConfigException when the artifact is missing, is not valid
     *                         PHP, does not return an array, does not
     *                         carry exactly the cache's fields, or does
     *                         not record a signature and a list table
     */
    public function read(): array
    {
        $file = $this->file;

        if (!is_file($file)) {
            throw new ConfigException(
                \sprintf('The route tree cache "%s" does not exist.', $file),
            );
        }

        try {
            $artifact = require $file;
        } catch (ParseError $parseError) {
            throw new ConfigException(
                \sprintf('The route tree cache "%s" is corrupted: it is not valid PHP.', $file),
                0,
                $parseError,
            );
        }

        if (!\is_array($artifact)) {
            throw new ConfigException(
                \sprintf('The route tree cache "%s" is corrupted: it does not return an array.', $file),
            );
        }

        foreach (array_keys($artifact) as $field) {
            if (isset(self::ARTIFACT_FIELDS[$field])) {
                continue;
            }

            throw new ConfigException(
                \sprintf('The route tree cache "%s" is corrupted: it carries the unknown field "%s".', $file, $field),
            );
        }

        foreach (array_keys(self::ARTIFACT_FIELDS) as $field) {
            if (\array_key_exists($field, $artifact)) {
                continue;
            }

            throw new ConfigException(
                \sprintf('The route tree cache "%s" is corrupted: it does not carry the "%s" field.', $file, $field),
            );
        }

        $signature = $artifact['signature'];

        if (!\is_string($signature) || $signature === '') {
            throw new ConfigException(
                \sprintf('The route tree cache "%s" is corrupted: it does not record a route table signature.', $file),
            );
        }

        $routes = $artifact['routes'];

        if (!\is_array($routes) || !array_is_list($routes)) {
            throw new ConfigException(
                \sprintf('The route tree cache "%s" is corrupted: its route table is not a list.', $file),
            );
        }

        return ['signature' => $signature, 'routes' => $routes, 'root' => $artifact['root']];
    }
}
