<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/** Parses bounded Accept header media ranges into quality values. */
final class AcceptHeaderParser
{
    /**
     * @return array<string, float>
     */
    public static function qualities(string $accept): array
    {
        $qualities = [];
        foreach (explode(',', mb_strtolower(mb_substr($accept, 0, 8192))) as $part) {
            $parsed = self::mediaRange(mb_trim($part));
            if ($parsed === null) {
                continue;
            }

            [$mediaType, $quality] = $parsed;
            $qualities[$mediaType] = max($qualities[$mediaType] ?? 0.0, $quality);
        }

        return $qualities;
    }

    /** @return null|array{string, float} */
    private static function mediaRange(string $part): ?array
    {
        if ($part === ''
            || preg_match('/^[a-z0-9!#$&^_.+*-]+\/[a-z0-9!#$&^_.+*-]+$/D', mb_trim(explode(';', $part, 2)[0])) !== 1
        ) {
            return null;
        }

        $segments = array_map('trim', explode(';', $part));
        $mediaType = array_shift($segments);

        return [$mediaType, self::quality($segments)];
    }

    /** @param list<string> $segments */
    private static function quality(array $segments): float
    {
        foreach ($segments as $segment) {
            if (!str_starts_with($segment, 'q=')) {
                continue;
            }

            $value = mb_substr($segment, 2);
            if (preg_match('/^(?:0(?:\.\d{0,3})?|1(?:\.0{0,3})?)$/D', $value) !== 1) {
                return 0.0;
            }

            return (float) $value;
        }

        return 1.0;
    }
}
