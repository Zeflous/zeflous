<?php

declare(strict_types=1);

/*
 * The module side file that throws while loading -- the loader's failure
 * capture refuses with the reason chained.
 */

throw new RuntimeException('the fixture file refuses to load');
