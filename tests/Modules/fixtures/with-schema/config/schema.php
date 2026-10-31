<?php

declare(strict_types=1);

/*
 * The schema the with-schema manifest declares: one required string and
 * one optional values entry -- enough for the block's two arms (a valid
 * block passes; a block without the required field refuses with the field
 * named).
 */

return [
    'endpoint' => ['required' => true, 'kind' => 'string'],
    'retries' => ['required' => false, 'kind' => 'strings'],
];
