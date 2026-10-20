<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

/** Splices the toolbar in front of the document's single closing body tag. */
final class DebugToolbarBodySplice
{
    /** @return null|string the spliced document, or null when it has no single closing body tag */
    public static function insertBeforeClosingBody(string $html, string $toolbar): ?string
    {
        $count = 0;
        $updated = preg_replace_callback(
            '/<\/body\s*>/i',
            static fn (array $match): string => $toolbar . $match[0],
            $html,
            1,
            $count,
        );

        if (!\is_string($updated) || $count !== 1) {
            return null;
        }

        return $updated;
    }
}
