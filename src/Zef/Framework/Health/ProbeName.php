<?php

declare(strict_types=1);

namespace Zef\Framework\Health;

/**
 * The health probe's name grammar: the sanitised spelling the
 * scrape's records carry -- normalised into the exposition alphabet
 * (`[A-Za-z0-9._-]`, everything else folding to the underscore),
 * capped at sixty-four characters, `unnamed` when the probe reports
 * the empty name.
 *
 * The unit mirrors the metrics lane's own name grammars ({@see
 * \Zef\Framework\Telemetry\PrometheusRenderer}'s metric stem): a
 * name the label grammar refuses is a scrape the consumer rejects
 * wholesale, never a probe silently dropped -- and the same
 * sanitised spelling is what makes the name a safe label value the
 * exposition carries.
 */
final class ProbeName
{
    /**
     * The name's own cap: the sixty-four characters the sanitised
     * spelling carries at most.
     */
    private const int NAME_CAP = 64;

    /**
     * The sanitised name the empty probe name answers.
     */
    private const string UNNAMED = 'unnamed';

    /**
     * The probe's name, sanitised into the exposition alphabet and
     * capped.
     */
    public static function sanitized(string $name): string
    {
        if ($name === '') {
            return self::UNNAMED;
        }

        return mb_substr(preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? self::UNNAMED, 0, self::NAME_CAP);
    }
}
