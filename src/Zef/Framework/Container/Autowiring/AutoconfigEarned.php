<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\InstanceDefinition;

/**
 * The earned tags one declaration may merge: the claims' verdict,
 * collision by collision (see {@see Autoconfiguration} for the apply
 * this serves).
 *
 * One tag, one source: a tag several sources would hand the
 * declaration -- a marker rule, an attribute, the declaration's own
 * `tags` key -- is the collision the boot refuses with every source
 * named, never a precedence quietly guessed (a framework upgrade that
 * makes a new rule cover an explicitly tagged class is a visible halt
 * and an actionable cleanup, not a silent rewrite of what the host
 * declared). The tags no source claims twice answer as the earned
 * list, ready to merge with the declaration's own.
 */
final readonly class AutoconfigEarned
{
    /**
     * @param array<string, list<string>> $claims tag => the sources claiming it
     *
     * @return list<string> the earned tags, in claim order
     */
    public static function tags(InstanceDefinition $instanceDefinition, array $claims): array
    {
        $autoTags = [];
        foreach ($claims as $tag => $sources) {
            $earned = $sources;
            if ($instanceDefinition->tags->carries($tag)) {
                $earned[] = "the definition's own tags key";
            }

            if (\count($earned) > 1) {
                throw AutoconfigRefusals::collision($instanceDefinition, $tag, $earned);
            }

            $autoTags[] = $tag;
        }

        return $autoTags;
    }
}
