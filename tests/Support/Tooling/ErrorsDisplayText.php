<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * Answers the text a log-supplied field may show in the operator's
 * terminal: control bytes stripped (an escape sequence or a carriage
 * return inside a stored level or code would otherwise ride the report
 * straight into the display), and the unknown spelling back when
 * nothing displayable remains.
 *
 * Lifted from `bin/zef`'s joined errors branch (#717's structural step)
 * so the report verb's display discipline is its own named unit, the
 * same posture every render fold in the inspection families carries.
 */
final class ErrorsDisplayText
{
    /**
     * The displayable spelling of a log-supplied field.
     *
     * @param string $value the raw field value as stored in the JSONL log
     *
     * @return string the control-byte-stripped spelling, or `unknown`
     *                when nothing displayable remains
     */
    public static function of(string $value): string
    {
        $stripped = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        return \is_string($stripped) && $stripped !== '' ? $stripped : 'unknown';
    }
}
