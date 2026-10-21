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
        /** @var mixed $error the spec's error axis: a scalar leaf or the parallel subtree */
        $error = $spec['error'];

        if (!\is_array($error)) {
            $spec['error'] = self::leafError($spec['tmp_name'] ?? null, $error);

            return $spec;
        }

        $downgraded = [];

        foreach ($error as $key => $leafError) {
            $downgraded[$key] = self::leafError(self::axis($spec['tmp_name'] ?? null, $key), $leafError);
        }

        // The parallel walk over the mixed error axis: every leaf's
        // verdict lands in a freshly typed list, never re-indexed
        // through the mixed axis itself.

        $spec['error'] = $downgraded;

        return $spec;
    }

    /**
     * One leaf's error axis after the ownership verdict: the disowned
     * error-OK entry (a temporary file the engine never wrote) downgrades
     * to `NO_FILE`, every other verdict passes untouched.
     *
     * @param mixed $temporaryPath the leaf's temporary-file axis value
     * @param mixed $leafError     the leaf's error axis value
     */
    private static function leafError(mixed $temporaryPath, mixed $leafError): mixed
    {
        if (
            $leafError === \UPLOAD_ERR_OK
            && \is_string($temporaryPath)
            && $temporaryPath !== ''
            && !is_uploaded_file($temporaryPath)
        ) {
            return \UPLOAD_ERR_NO_FILE;
        }

        return $leafError;
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
