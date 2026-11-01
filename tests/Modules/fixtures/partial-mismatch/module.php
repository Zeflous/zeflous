<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\DemandingIdentityModule;

/*
 * The half-lying manifest: it names the class's own name and carries the
 * class's own requirements, but answers a different version -- the
 * agreement check's partial-disagreement arm, where half the record agrees
 * and the identity is still two identities.
 */

return [
    'name' => 'test/demanding',
    'version' => '1.5.0',
    'requires' => ['zef/alpha', 'zef/beta'],
    'class' => DemandingIdentityModule::class,
];
