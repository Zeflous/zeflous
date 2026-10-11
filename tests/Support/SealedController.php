<?php

declare(strict_types=1);

namespace Zef\Test\Support;

/**
 * Deterministic controller double whose only action is private: resolving
 * `SealedController@reveal` must fail closed, because a private method is
 * not a callable the dispatcher may invoke.
 */
final class SealedController
{
    /**
     * Kept public and unused so static analysis sees the private method
     * exercised by its own class (the double exists to be refused).
     */
    public function surface(): string
    {
        return $this->reveal();
    }

    private function reveal(): string
    {
        return 'never reachable through the dispatcher';
    }
}
