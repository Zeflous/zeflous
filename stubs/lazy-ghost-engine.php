<?php

declare(strict_types=1);

/*
 * Static-analysis stub for the lazy lane's ghost-return fixture
 * (`Zef\Test\Support\Container\Lazy\GhostEngine`): the fixture factory
 * declares the class as its return type so the class-existence refusal
 * has a real declaration to refuse, but no runtime file ever defines
 * it -- the stub keeps phpstan and psalm honest about the signature
 * while the boot's own check answers 'does not exist'. The ext-redis
 * stub's own posture, mirrored: a class the analysers see and the
 * runtime may not carry.
 */

namespace Zef\Test\Support\Container\Lazy;

final class GhostEngine
{
}
