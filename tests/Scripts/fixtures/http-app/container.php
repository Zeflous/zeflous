<?php

declare(strict_types=1);

/*
 * The HTTP inspection fixture application's container mode:
 * production, the posture the AOT compile/dump lane may dump under --
 * so the boot's throwaway artifact is actually written and its
 * finally-owned cleanup is observable rather than vacuous.
 */

return [
    'mode' => 'production',
];
