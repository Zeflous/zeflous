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
 *
 * @api the module author's own identity spelling -- `metadata()` builds
 *      this object from module code that lives outside this repository
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
        self::asserted($this->name);
    }

    /**
     * The grammar's own assertion for an already-typed spelling -- the
     * callers that hold a string and need it refused-or-carried use this
     * spelling, the constructor's validation without the instance.
     *
     * @throws ModuleException when the spelling breaks the grammar
     */
    public static function asserted(string $spelling): void
    {
        $segments = explode('/', $spelling);

        if (\count($segments) !== 2) {
            throw ModuleExceptionSpelling::notTwoSegments($spelling);
        }

        foreach ($segments as $segment) {
            self::assertSegment($spelling, $segment);
        }
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

    private static function assertSegment(string $name, string $segment): void
    {
        if ($segment === '' || mb_strlen($segment) > self::SEGMENT_CEILING) {
            throw ModuleExceptionSpelling::segmentCeiling($segment, $name);
        }

        if (preg_match(self::PATTERN, $segment) !== 1) {
            throw ModuleExceptionSpelling::segmentGrammar($segment, $name);
        }
    }
}
