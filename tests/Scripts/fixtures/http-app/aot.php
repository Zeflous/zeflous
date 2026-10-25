<?php

declare(strict_types=1);

/*
 * The HTTP inspection fixture application's AOT knob: the
 * compile/dump lane enabled, so the boot's throwaway artifact is
 * actually written and its finally-owned cleanup is observable
 * rather than vacuous.
 */

return [
    'enabled' => true,
];
