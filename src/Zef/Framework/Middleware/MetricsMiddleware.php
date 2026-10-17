<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\Health\HealthAggregator;
use Zef\Framework\Health\HealthExposition;
use Zef\Framework\Health\HealthGroups;
use Zef\Framework\Health\HealthStatuses;
use Zef\Framework\Http\MetricsPolicy;
use Zef\Framework\Http\Response;
use Zef\Framework\Telemetry\MeterInterface;
use Zef\Framework\Telemetry\PrometheusRenderer;

/**
 * Metrics middleware -- the exposition boundary, the HTTP stack's
 * eleventh PSR-15 middleware and the observability campaign's fifth
 * and closing family: the surface where the metrics lane's
 * accumulated series answer a scrape, and the health seam's probes
 * answer a poll.
 *
 * THE BOUNDARY IS THE COMPOSITION, exactly the propagation
 * boundary's own reason ({@see CorrelationMiddleware}): every
 * answer this middleware gives flows from the two engines -- the
 * {@see MeterInterface} the render reads and the {@see
 * HealthAggregator} the health surface polls -- so its face lives
 * in the composition lane, not the engine-free HTTP layer. The two
 * engines meet one face the way the tracer met the propagation
 * boundary's: the engines stay config-blind, the policy stays
 * engine-blind, and this seam is the one place the two meet.
 *
 * THE SURFACE IS FOUR ENDPOINTS, judged by the exact request path:
 * the exposition ({@see MetricsPolicy::$metricsPath}) answering the
 * `text/plain` exposition format over the meter's snapshot; the
 * health base ({@see MetricsPolicy::$healthPath}) answering the
 * whole registry's aggregate; and the two views deriving from it
 * (`/live`, `/ready`) answering the grouped aggregates the
 * load-balancer contracts spell. The judgement is exact-path and
 * GET-only: every other method passes through to the handler
 * untouched -- the endpoints are read surfaces, and a write method
 * addressed at them is the terminal handler's business, not the
 * family's. A HEAD request rides the stack's own head middleware
 * ({@see \Zef\Framework\Http\HeadMiddleware}): composed above, it
 * folds the HEAD onto the GET this middleware answers and empties
 * the body on the way out -- the composition the host's stack owns.
 *
 * THE STATUS CODE IS THE LOAD BALANCER'S CONTRACT: the health
 * surface answers 200 when every probe passed and 503 when any
 * probe failed, crashed or was skipped -- the status the traffic
 * router gates on, carried verbatim from the reference's own
 * aggregate handler. Both surfaces answer `Cache-Control: no-store`
 * -- a scrape and a poll are live readings, never a cached document
 * -- and the exposition carries the format's own content type, the
 * `version=0.0.4` parameter the consumers negotiate.
 *
 * SECURE DEFAULT ({@see MetricsPolicy}): the family ships OFF -- the
 * endpoints are publications (an `/metrics` answers the internal
 * series names and their dimensions; a `/health` answers the
 * dependency topology the probes reveal), and a publication knob's
 * safe default is the one that publishes nothing. A disarmed family
 * passes every request through untouched: the surfaces exist only
 * when the deployment armed them, the fourth and closing decision in
 * the campaign's fail-closed chain.
 *
 * Ported from the reference implementation (legacy `zef-framework-src`
 * `modules/Health/AggregateHealthHandler.php` -- the 200-ok/503-degraded
 * contract, the `application/json` + `no-store` answer -- and legacy
 * `zef-main` `Module/Health`'s live/ready surface): the endpoints
 * arrive as the family's own path-scoped PSR-15 face because this
 * repository's routing runtime has not landed yet -- the
 * pre-routing endpoint pattern the CORS preflight already models --
 * and the port's deviation is recorded in the campaign log.
 */
final readonly class MetricsMiddleware implements MiddlewareInterface
{
    /** The exposition answer's content type: the format version the
     * consumers negotiate, the charset the text format spells. */
    private const string EXPOSITION_CONTENT_TYPE = 'text/plain; version=0.0.4; charset=utf-8';

    /** The health answer's content type. */
    private const string HEALTH_CONTENT_TYPE = 'application/json';

    /** The live view's suffix on the health base path. */
    private const string LIVE_SUFFIX = '/live';

    /** The ready view's suffix on the health base path. */
    private const string READY_SUFFIX = '/ready';

    /**
     * @param MetricsPolicy      $metricsPolicy      the family's frozen knob set
     * @param MeterInterface     $meter              the metrics engine whose
     *                                               snapshot renders
     * @param PrometheusRenderer $prometheusRenderer the exposition renderer
     * @param HealthAggregator   $healthAggregator   the health engine the
     *                                               surface polls
     */
    public function __construct(
        private MetricsPolicy $metricsPolicy,
        private MeterInterface $meter,
        private PrometheusRenderer $prometheusRenderer,
        private HealthAggregator $healthAggregator,
    ) {
    }

    #[Override]
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        if (!$this->metricsPolicy->enabled) {
            return $handler->handle($request);
        }

        $exposition = $this->answer($request);
        if ($exposition instanceof Response) {
            return $exposition;
        }

        return $handler->handle($request);
    }

    /**
     * The family's own answer for the request, when the request
     * addresses one of the four endpoints: the exposition over the
     * meter's snapshot, or the aggregate JSON over the health
     * registry -- null when the request addresses none of them and
     * travels on to the handler.
     */
    private function answer(ServerRequestInterface $request): ?Response
    {
        if ($request->getMethod() !== 'GET') {
            return null;
        }

        $path = $request->getUri()->getPath();

        if ($path === $this->metricsPolicy->metricsPath) {
            return $this->exposition();
        }

        return $this->health($path);
    }

    /**
     * The exposition answer: the meter's accumulated series rendered
     * into the text exposition format -- an empty snapshot answers
     * the empty body, the render's own honest shape (no HELP or TYPE
     * line a series did not earn).
     */
    private function exposition(): Response
    {
        return new Response(
            200,
            [
                'Content-Type' => self::EXPOSITION_CONTENT_TYPE,
                'Cache-Control' => 'no-store',
            ],
            $this->prometheusRenderer->render($this->meter),
        );
    }

    /**
     * The health answer for the path, when the path is one of the
     * health surface's three endpoints: the aggregate JSON for the
     * view the path names, the status code the load balancer gates
     * on -- null when the path names none of them.
     */
    private function health(string $path): ?Response
    {
        $healthPath = $this->metricsPolicy->healthPath;

        if ($path === $healthPath) {
            return $this->healthResponse(
                $this->healthAggregator->aggregate($this->metricsPolicy->timeoutMs),
            );
        }

        if ($path === $healthPath . self::LIVE_SUFFIX) {
            return $this->healthResponse(
                $this->healthAggregator->aggregateFor(HealthGroups::GROUP_LIVE, $this->metricsPolicy->timeoutMs),
            );
        }

        if ($path === $healthPath . self::READY_SUFFIX) {
            return $this->healthResponse(
                $this->healthAggregator->aggregateFor(HealthGroups::GROUP_READY, $this->metricsPolicy->timeoutMs),
            );
        }

        return null;
    }

    /**
     * One health view's answer: the aggregate JSON under the scrape's
     * deadline knob, the 200 the passing registry earns and the 503
     * the degraded one answers -- the aggregate report the view
     * renders, the shape the aggregator's own contract spells.
     *
     * @param array{status:string,checks:list<array{name:string,status:string,message:string}>,tookMs:float} $report
     */
    private function healthResponse(array $report): Response
    {
        return new Response(
            $report['status'] === HealthStatuses::STATUS_OK ? 200 : 503,
            [
                'Content-Type' => self::HEALTH_CONTENT_TYPE,
                'Cache-Control' => 'no-store',
            ],
            HealthExposition::json($report),
        );
    }
}
