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
        $composerJson = self::decodeObjectFile($composerJsonPath, 'composer.json');
        $composerLock = self::decodeObjectFile($composerLockPath, 'composer.lock');

        return new self(
            self::sectionOrThrow(
                $composerJson['require'] ?? [],
                'composer.json require section is malformed: it must be an object.',
            ),
            self::sectionOrThrow(
                $composerLock['packages'] ?? [],
                'composer.lock packages section is malformed: it must be an array.',
            ),
        );
    }

    /**
     * @return array<mixed>
     */
    private static function sectionOrThrow(mixed $section, string $message): array
    {
        // A present-but-non-array section is a broken manifest and fails
        // closed instead of silently passing the gate. An absent section
        // arrives here as an empty array and passes through: it means "no
        // dependencies declared", which the gate then judges on its own.
        return \is_array($section) ? $section : throw new ToolingException($message);
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

        return self::objectOrThrow(json_decode($payload, true), $label, $path);
    }

    /**
     * @return array<mixed>
     */
    private static function objectOrThrow(mixed $decoded, string $label, string $path): array
    {
        // A Composer manifest must be a JSON *object*. Decoding associatively
        // maps objects to arrays, so a scalar, null or malformed payload
        // decodes to a non-array; a non-empty list means the payload was a
        // top-level JSON array. Both shapes are rejected so a broken manifest
        // can never masquerade as an empty one and silently pass the gate.
        if (!\is_array($decoded)) {
            throw new ToolingException(\sprintf('%s does not contain a JSON object: %s', $label, $path));
        }

        if ($decoded !== [] && array_is_list($decoded)) {
            throw new ToolingException(\sprintf('%s does not contain a JSON object: %s', $label, $path));
        }

        return $decoded;
    }
}
