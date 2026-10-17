<?php

declare(strict_types=1);

namespace Zef\Framework\Health;

use Closure;
use InvalidArgumentException;
use Override;
use Throwable;
use Zef\Framework\Contracts\HealthCheckInterface;

/**
 * Wraps a health check with a per-probe time budget -- the metrics
 * family's own timeout unit over this repository's already-live
 * health seam.
 *
 * The timeout is enforced by the injected `$timeoutRunner`: a
 * `Closure(callable, float): mixed` that runs the probe under a
 * deadline -- pass the async runtime's timeout primitive for a REAL
 * interruption of a hung probe. Without a runner the probe runs
 * directly (no interruption possible for synchronous code); pair the
 * scrape with the {@see HealthAggregator}'s own overall deadline to
 * bound the response anyway.
 *
 * OUTCOME CONTAINMENT: a runner timeout or a crashing probe answers
 * `false` -- never a throw -- so the aggregate's status math stays
 * deterministic. This repository's boolean probe seam carries no
 * message ({@see Contracts\HealthCheckInterface}), so the reference's
 * timeout diagnostics (`probe did not finish within Ns`) fold to the
 * boolean the seam answers: the timeout is observable as the check
 * being down, and the aggregate's own crash containment is what
 * names the failure class for the probes that throw outright.
 *
 * The wrapped check's GROUP is delegated, not lost: a grouped check
 * wrapped for its timeout keeps the view it declared -- the
 * reference's own wrapper dropped the group (an unwrapped `live`
 * probe read as `ready` the moment it gained a timeout), and this
 * port refuses the loss: the wrapper implements {@see
 * GroupedHealthCheckInterface} and answers the inner check's own
 * group, `ready` for a plain check exactly as the aggregate's own
 * default reads it.
 */
final readonly class TimeoutHealthCheck implements GroupedHealthCheckInterface
{
    /**
     * @param HealthCheckInterface                         $healthCheck    the wrapped probe
     * @param float                                        $timeoutSeconds the probe's own budget, seconds
     * @param null|Closure(callable(): bool, float): mixed $timeoutRunner  the timeout-enforcing
     *                                                                     runner; null runs the
     *                                                                     probe directly
     *
     * @throws InvalidArgumentException when the budget is not positive
     */
    public function __construct(
        private HealthCheckInterface $healthCheck,
        private float $timeoutSeconds = 1.0,
        private ?Closure $timeoutRunner = null,
    ) {
        if ($timeoutSeconds <= 0) {
            throw new InvalidArgumentException('The health check timeout must be a positive number of seconds.');
        }
    }

    #[Override]
    public function name(): string
    {
        return $this->healthCheck->name();
    }

    #[Override]
    public function healthGroup(): string
    {
        if ($this->healthCheck instanceof GroupedHealthCheckInterface) {
            return $this->healthCheck->healthGroup();
        }

        return HealthGroups::GROUP_READY;
    }

    #[Override]
    public function isHealthy(): bool
    {
        if (!$this->timeoutRunner instanceof Closure) {
            return $this->healthCheck->isHealthy();
        }

        $inner = $this->healthCheck;
        $seconds = $this->timeoutSeconds;

        try {
            return $this->narrowed(($this->timeoutRunner)(
                static fn (): bool => $inner->isHealthy(),
                $seconds,
            ));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The runner's raw answer narrowed onto the seam's own boolean
     * vocabulary: a runner may answer anything (the timeout primitive
     * of an async runtime reports its own failures its own way), and
     * everything but the boolean reads as down.
     *
     * @param mixed $result the runner's raw answer
     */
    private function narrowed(mixed $result): bool
    {
        return \is_bool($result) ? $result : false;
    }
}
