<?php

declare(strict_types=1);

namespace Zef\Test\Support\Telemetry;

use Closure;
use Zef\Framework\Telemetry\SpanData;

/**
 * Recording end double: the span's end-callback seam as a first-class
 * object -- the records the span hands over land in a list a test
 * reads directly, and the closure the span's constructor accepts is
 * the object's own invokable.
 *
 * The by-reference closure capture this double replaces is the same
 * unread-capture the timing family's stepping clock exists to avoid:
 * a test-visible seam the coding standard's unused-variable analysis
 * can see, rather than a `use (&$captured)` write the analysers
 * cannot.
 */
final class RecordingEnd
{
    /** @var list<SpanData> the records handed over, in end order */
    public array $records = [];

    public function __invoke(SpanData $spanData): void
    {
        $this->records[] = $spanData;
    }

    /**
     * The end-callback the span's constructor accepts.
     *
     * @return Closure(SpanData):void
     */
    public function closure(): Closure
    {
        return $this(...);
    }
}
