<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The artifact boundary's render refusal: a value the boundary cannot
 * carry as code or data -- the verdict the compile lane *degrades* on
 * rather than crashes ({@see ContainerAotCache::dumpPlanOrDegrade()}).
 *
 * The refusal's own message keeps every way out: move the computation
 * behind the factory shape, or boot without the AOT lane. The compile
 * lane's degrade automates the lane's own posture for the
 * constructor-default case the plan bakes verbatim: the boot already
 * answered through the full lane, so the honest answer is no artifact
 * for the next boot -- never a crashed boot for a plan the boundary
 * cannot carry. Environmental failures (an unwritable cache, a missing
 * directory) stay {@see AotException}: they are the operator's to fix,
 * not the plan's to route around.
 */
final class AotUnrenderableException extends AotException
{
}
