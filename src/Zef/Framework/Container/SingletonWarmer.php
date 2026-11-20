<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The warmup orchestration the scope & lifecycle area deferred to the
 * compiler-pass lane (ROADMAP area 5's KDD, area 8's knob): the eager
 * construction of every singleton-lifetime definition, once, right
 * after the install loop -- so the first request never pays the
 * construction costs and a definition that cannot construct at all
 * halts the boot it belongs to instead of the first request it would
 * have failed.
 *
 * The scope is exactly the singleton lane: a request-lifetime
 * definition belongs to a scope the boot does not own, and a transient
 * one is *defined* by its fresh-per-resolution answer -- warming either
 * would be a contradiction, not an optimisation. The lane is opt-in
 * (`aot.warm`) and runs identically over both boots, the full compile
 * and the artifact load, because what it warms is the container's
 * memoisation, which both lanes install through the same
 * {@see Container::set()}.
 *
 * A definition whose construction throws halts the boot with the
 * construction failure's own message: the warmer adds no wrapping,
 * because the failure the host must see is the one the constructor
 * already raised.
 *
 * The lazy lane's marks stay deferred across the warm step (issue
 * #800's finding 2, the interaction documented): a lazy-marked
 * singleton resolves through the ordinary lane at warm time, so the
 * warmer answers the native proxy alone -- the memo holds the
 * deferral, the factory never runs, and the halt-the-boot promise
 * above reads the EAGER singleton lane alone. This is the recorded
 * posture the never-at-the-boot families spell through it (the
 * `database.connection` and `database.schema` registrations, the
 * migrator -- {@see FrameworkDefaults}: silent knobs whose refusal
 * must fire at the host's own ask, never at the boot), so the
 * deferral is the declaration's own, kept exactly as spelled when the
 * warm knob asks for proof: a host whose lazy singleton fails
 * construction hears it at the first use, with the deferral they
 * declared -- and a host who wants the boot's own proof leaves the
 * `lazy` key off the declaration the warm knob then proves. The
 * warmer itself constructs nothing a deferral owns: it resolves, and
 * the resolution lane's own answer is the proxy.
 */
final readonly class SingletonWarmer
{
    /**
     * Eagerly constructs each named singleton through the container's
     * own resolution lane -- the lazy-marked half of the walk answered
     * by the native proxy its deferral owns, the eager half by the
     * construction the promise above names.
     *
     * @param list<string> $singletonIds the singleton-lifetime identifiers,
     *                                   in declaration order
     */
    public static function warm(array $singletonIds, Container $container): void
    {
        foreach ($singletonIds as $singletonId) {
            $container->get($singletonId);
        }
    }
}
