<?php

declare(strict_types=1);

namespace Zef\Framework\Health;

use JsonException;

/**
 * The health report's exposition rendering: one gauge per probe plus
 * the aggregate status -- the unit the scrape's consumers read,
 * mirroring the metrics lane's own render unit ({@see
 * \Zef\Framework\Telemetry\PrometheusRenderer}) exactly as the
 * campaign's closing family carries both faces.
 *
 * The probe names are sanitised into the exposition alphabet by the
 * aggregation pipeline itself ({@see ProbeName}), so they are safe
 * label values; the gauges are the load balancer's own reading (1 =
 * up, 0 = down; 1 = ok, 0 = degraded).
 */
final class HealthExposition
{
    /**
     * The whole scrape's JSON rendering, the shape the health
     * endpoint answers.
     *
     * @param array{status:string,checks:list<array{name:string,status:string,message:string}>,
     *            tookMs:float} $report the aggregate report
     *
     * @throws JsonException when the report cannot render
     */
    public static function json(array $report): string
    {
        return json_encode(
            $report,
            \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Renders the aggregate report into the Prometheus text
     * exposition format.
     *
     * @param array{status:string,checks:list<array{name:string,status:string,message:string}>,
     *            tookMs:float} $report the aggregate report
     */
    public static function render(array $report): string
    {
        $lines = [
            '# HELP zef_health_check Outcome of each health probe (1 = up, 0 = down).',
            '# TYPE zef_health_check gauge',
        ];
        foreach ($report['checks'] as $check) {
            $lines[] = \sprintf(
                'zef_health_check{check="%s"} %s',
                $check['name'],
                $check['status'] === HealthStatuses::STATUS_UP ? '1' : '0',
            );
        }

        $lines[] = '# HELP zef_health_status Aggregate health status (1 = ok, 0 = degraded).';
        $lines[] = '# TYPE zef_health_status gauge';
        $lines[] = 'zef_health_status ' . ($report['status'] === HealthStatuses::STATUS_OK ? '1' : '0');

        return implode("\n", $lines) . "\n";
    }
}
