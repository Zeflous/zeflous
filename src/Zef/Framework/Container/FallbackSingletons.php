<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The fallback lane's singleton half: the memoised answer-or-build the
 * miss path's own resolution defers behind -- a fresh {@see
 * SingletonMemo} over the registry's own ids (the registry keeps its
 * OWN memo, separate from the store's, because the fallback ids are
 * nobody else's), the wider-lifetime frame riding the guarded
 * construction inside the memo's build step exactly the way the
 * registered singleton lane's frame does.
 *
 * Split from {@see NamespaceFallbacks} when the registry's own read
 * surface (the lifetime probe's fallback half) pushed the class past
 * the architecture gate's difficulty ceiling -- the seams split the
 * questions: the registry keeps registration, coverage and the
 * lifetime read; this unit keeps the memoised singleton answer and its
 * forgetting (a replaced factory's own invalidation, a fresh memo
 * because the per-identifier keys the old one carries cannot be
 * attributed back to the prefix that built them).
 */
final class FallbackSingletons
{
    private SingletonMemo $singletonMemo;

    public function __construct(
        private readonly FallbackConstructions $fallbackConstructions,
        private readonly ResolutionFrames $resolutionFrames,
    ) {
        $this->singletonMemo = new SingletonMemo();
    }

    /**
     * The singleton lane's answer: the memo's answer-or-build over the
     * guarded construction, the wider-lifetime frame open at the build
     * the same way the registered lane's is.
     *
     * @param Closure(Container, string): mixed $factory receives the container and the missed identifier
     */
    public function answer(string $id, Closure $factory, Container $container): mixed
    {
        return $this->singletonMemo->answer(
            $id,
            fn (): mixed => $this->resolutionFrames->withinSingleton(
                $id,
                fn (): mixed => $this->fallbackConstructions->run($id, $factory, $container),
            ),
        );
    }

    /**
     * Forgets every memoised fallback answer -- a replaced factory's
     * own invalidation (a fresh memo, because the per-identifier keys
     * the old one carries cannot be attributed back to the prefix
     * that built them).
     */
    public function forget(): void
    {
        $this->singletonMemo = new SingletonMemo();
    }
}
