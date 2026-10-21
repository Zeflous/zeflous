<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Anchors the engine-sourced upload bag to the engine's own upload claim.
 *
 * {@see RequestFactory::fromGlobals()} is the seam that claims its bags
 * ARE the engine's superglobal state, and on that seam the engine's own
 * testimony is checkable: a file the SAPI actually accepted through a
 * request body answers {@see is_uploaded_file()} true. An error-OK
 * entry whose temporary file the engine never wrote is the ghost
 * upload the leaf builder already downgrades ({@see UploadLeaf} -- a
 * body PHP never actually wrote reads as `NO_FILE`), and this
 * collaborator extends the same doctrine over the raw bag before it
 * enters the source-agnostic normalisation: the disowned entry is
 * downgraded to `NO_FILE`, the one honest answer for a payload the
 * engine disowns. A bag that lies about what the engine wrote stops
 * carrying a stream over whatever path it named.
 *
 * The explicitly-sourced twin ({@see RequestFactory::fromServer()}) is
 * deliberately NOT anchored: a persistent worker that decodes its own
 * multipart bodies materialises temporary files the engine never
 * uploaded, and that lane's contract -- every source supplied
 * explicitly, never a superglobal fallback -- owns its own trust.
 */
final class UploadOrigin
{
    /**
     * Anchors one engine-sourced upload specification bag.
     *
     * @param array<int|string, mixed> $files the raw upload specification, typically $_FILES
     *
     * @return array<int|string, mixed> the bag with every disowned entry downgraded
     */
    public static function anchor(array $files): array
    {
        foreach ($files as $field => $spec) {
            if (!\is_array($spec) || !\array_key_exists('error', $spec)) {
                continue;
            }

            $files[$field] = self::walk($spec);
        }

        return $files;
    }

    /**
     * Walks one specification node, downgrading the disowned leaves in
     * place -- the error axis drives the recursion exactly as the
     * normalisation's own walk reads it ({@see UploadNode}).
     *
     * @param array<int|string, mixed> $spec the node's parallel axes
     *
     * @return array<int|string, mixed> the walked node
     */
    private static function walk(array $spec): array
    {
        $error = $spec['error'];

        if (!\is_array($error)) {
            $temporaryPath = $spec['tmp_name'] ?? null;

            if (
                $error === \UPLOAD_ERR_OK
                && \is_string($temporaryPath)
                && $temporaryPath !== ''
                && !is_uploaded_file($temporaryPath)
            ) {
                $spec['error'] = \UPLOAD_ERR_NO_FILE;
            }

            return $spec;
        }

        foreach ($error as $key => $leafError) {
            $spec['error'][$key] = self::walk([
                'name' => self::axis($spec['name'] ?? null, $key),
                'type' => self::axis($spec['type'] ?? null, $key),
                'tmp_name' => self::axis($spec['tmp_name'] ?? null, $key),
                'error' => $leafError,
                'size' => self::axis($spec['size'] ?? null, $key),
            ])['error'];
        }

        return $spec;
    }

    /**
     * Answers the value one parallel axis carries for a key, or null
     * when the axis is not an array or lacks the key.
     *
     * @param mixed $axis the parallel axis at this node
     */
    private static function axis(mixed $axis, int|string $key): mixed
    {
        return \is_array($axis) ? ($axis[$key] ?? null) : null;
    }
}
