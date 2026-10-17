<?php

declare(strict_types=1);

namespace Zef\Framework\Health;

/**
 * The health scrape's status vocabulary: the aggregate statuses and
 * the per-check statuses the report carries -- the one home every
 * reading, judgement and rendering references, so the scrape's
 * engines ({@see HealthAggregator}, {@see HealthProbe}), its
 * renderings ({@see HealthExposition}) and its HTTP face all spell
 * the same four words.
 */
final class HealthStatuses
{
    /** The aggregate status when every probe passed. */
    public const string STATUS_OK = 'ok';

    /** The aggregate status when any probe failed, crashed or was skipped. */
    public const string STATUS_DEGRADED = 'degraded';

    /** The per-check status a healthy probe answers. */
    public const string STATUS_UP = 'up';

    /** The per-check status a failed, crashed or skipped probe answers. */
    public const string STATUS_DOWN = 'down';
}
