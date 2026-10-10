<?php

declare(strict_types=1);

namespace Zef\Framework\Tooling;

/**
 * The production dependency declaration of a Composer repository.
 *
 * Value object read from `composer.json` (the `require` section) and
 * `composer.lock` (the production `packages` array). It is the input of the
 * zero-dependency gate ({@see ZeroDependencyGate}); the gate logic stays pure
 * because this class is the only place that knows the Composer file shapes.
 */
final readonly class ComposerManifest
{
    public function __construct(
        /** @var array<mixed> */
        public array $require,
        /** @var array<mixed> */
        public array $packages,
    ) {
    }

    public static function fromFiles(string $composerJsonPath, string $composerLockPath): self
    {
        return new self(
            self::requireOf(self::decodeObjectFile($composerJsonPath, 'composer.json')),
            self::packagesOf(self::decodeObjectFile($composerLockPath, 'composer.lock')),
        );
    }

    /**
     * @param array<mixed> $composerJson
     *
     * @return array<mixed>
     */
    private static function requireOf(array $composerJson): array
    {
        // An absent "require" section means no dependencies are declared; the
        // gate then fails the exact-match check on an empty array, so no
        // special case for the absent key is needed here.
        $require = $composerJson['require'] ?? [];

        return \is_array($require) ? $require : throw new ToolingException(
            'composer.json require section is malformed: it must be an object.',
        );
    }

    /**
     * @param array<mixed> $composerLock
     *
     * @return array<mixed>
     */
    private static function packagesOf(array $composerLock): array
    {
        // An absent "packages" key means the lock declares no production
        // packages; a present-but-non-array key is a broken lock and fails
        // closed instead of silently passing the gate.
        $packages = $composerLock['packages'] ?? [];

        return \is_array($packages) ? $packages : throw new ToolingException(
            'composer.lock packages section is malformed: it must be an array.',
        );
    }

    /**
     * @return array<mixed>
     */
    private static function decodeObjectFile(string $path, string $label): array
    {
        if (!is_file($path)) {
            throw new ToolingException(\sprintf('%s not found: %s', $label, $path));
        }

        $raw = file_get_contents($path);
        $payload = \is_string($raw) ? $raw : '';

        // A Composer manifest must be a JSON *object*. Decoding associatively
        // maps objects to arrays, so a scalar, null or malformed payload
        // decodes to a non-array; a non-empty list means the payload was a
        // top-level JSON array. Both shapes are rejected so a broken manifest
        // can never masquerade as an empty one and silently pass the gate.
        $decoded = json_decode($payload, true);

        if (!\is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new ToolingException(\sprintf('%s does not contain a JSON object: %s', $label, $path));
        }

        return $decoded;
    }
}
