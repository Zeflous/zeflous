<?php

declare(strict_types=1);

namespace Zef\Framework\Modules;

/**
 * The module name grammar: two `/`-joined segments, each lowercase and
 * `vendor/name`-shaped -- the identity every other rule derives from.
 *
 * A name is exactly two segments, each 1-32 bytes of `[a-z0-9]` edges with
 * `-` and `_` allowed inside, so the whole name sits under a 65-byte
 * ceiling and maps onto its configuration namespace injectively: the one
 * `/` becomes the one `.` of the `zef.payment`-shaped configuration key,
 * and a segment's own lack of dots keeps two distinct module names from
 * ever folding onto one key. Uppercase spellings are refused rather than
 * normalised -- the module's name appears in refusals, in configuration
 * keys and in the manifest's `requires` list, and one canonical spelling
 * is the only way all three agree.
 */
final readonly class ModuleName
{
    private const string PATTERN = '/^[a-z0-9](?:[a-z0-9_-]{0,30}[a-z0-9])?$/D';

    private const int SEGMENT_CEILING = 32;

    /**
     * @throws ModuleException when the spelling is not two `/`-joined
     *                         segments of the grammar above
     */
    public function __construct(
        public string $name,
    ) {
        $segments = explode('/', $this->name);

        if (\count($segments) !== 2) {
            throw ModuleExceptionSpelling::notTwoSegments($this->name);
        }

        foreach ($segments as $segment) {
            $this->assertSegment($segment);
        }
    }

    /**
     * The module's configuration namespace: the name's single `/` spelled
     * as a `.` -- the key the module's own configuration block lives
     * under, and the only address the engine reads for it.
     */
    /**
     * The grammar's own assertion for an already-typed spelling -- the
     * callers that hold a string and need it refused-or-carried use this
     * spelling, the constructor's validation without the instance.
     *
     * @throws ModuleException when the spelling breaks the grammar
     */
    public static function asserted(string $spelling): void
    {
        new self($spelling);
    }

    /**
     * The dotted key an already-validated name derives -- the boot's own
     * reads use this spelling, the name's grammar having already answered
     * for the spelling they carry.
     */
    public static function configKey(string $name): string
    {
        return str_replace('/', '.', $name);
    }

    private function assertSegment(string $segment): void
    {
        if ($segment === '' || mb_strlen($segment) > self::SEGMENT_CEILING) {
            throw ModuleExceptionSpelling::segmentCeiling($segment, $this->name);
        }

        if (preg_match(self::PATTERN, $segment) !== 1) {
            throw ModuleExceptionSpelling::segmentGrammar($segment, $this->name);
        }
    }
}
