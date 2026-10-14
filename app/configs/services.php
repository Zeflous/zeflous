<?php

declare(strict_types=1);

/*
 * The container's configuration file: everything under this key is the
 * `services` map the framework's container bootstrapper installs at boot.
 *
 * Each entry is one declarative definition, keyed by its service
 * identifier. Four mutually-exclusive shapes exist, and exactly one must
 * be chosen per entry:
 *
 *   ['class' => ClassName::class, 'arguments' => [...]]
 *       Constructs ClassName with the positional arguments. An argument
 *       written as the single-key map ['service' => 'other.id'] is
 *       resolved through the container; every other value is a literal.
 *
 *   ['factory' => 'Factory::method', 'arguments' => [...]]
 *       Builds the service by calling the named factory method with the
 *       positional arguments (a factory method already registered as a
 *       service wins over the static call).
 *
 *   ['alias' => 'other.id']
 *       A second name for an already-declared service.
 *
 *   ['value' => <any>]
 *       Registers a pre-built value as a service.
 *
 * The whole map is validated fail-closed before a single definition is
 * installed: unknown shape keys, unknown service references and circular
 * dependency chains halt the boot with the offending entry named.
 *
 * The shipped profile declares no services -- the framework ships none of
 * its own -- which boots a valid empty container; an application fills
 * this file with the services it wants constructed from configuration.
 */

return [];
