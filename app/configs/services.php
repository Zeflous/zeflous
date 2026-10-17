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
 *   ['class' => ClassName::class, 'arguments' => ['param' => ...]]
 *       The name-keyed hybrid: the named parameters are the declaration's
 *       own spellings, the gaps are planned by the autowiring compile
 *       pass (the constructor's attributes and type hints, its own
 *       defaults, or the honest refusal).
 *
 *   ['class' => ClassName::class, 'autowire' => true]
 *       The whole-constructor planning request: every parameter planned
 *       by the pass. An unregistered instantiable class a parameter's
 *       type names is birthed as its own declared definition, on the
 *       record; an unbound interface is refused with every way out.
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
 * The class and factory shapes also accept a 'lifetime' key -- the
 * sharing policy, one of 'singleton' (the default: one instance for
 * the container's whole life), 'request' (one instance per request
 * scope, resolved through Container::createRequestScope(), refused at
 * the root with the way out in the message) or 'transient' (a fresh
 * instance every resolution). An alias has no lifetime of its own (it
 * rides its target's), and a value is a constant; a singleton that
 * depends on a request-scoped service is refused at boot -- the
 * captive dependency -- rather than silently captured forever.
 *
 * The class and factory shapes also accept a 'tags' key -- the
 * grouping labels a declaration carries, a list of tag strings (the
 * grammar [A-Za-z0-9._-]{1,128}, no tag twice). The read side lives in
 * the container under the reserved 'framework.tags' service: a
 * TaggedServices locator answering has()/ids()/services()/one() over
 * everything the boot installed (an alias and a value refuse the key:
 * the tag belongs on the definition that builds the service).
 *
 * Two automatic spellings exist beside the key (the autoconfiguration
 * lane): a class shape whose class implements an interface named in the
 * 'tags' > 'interfaces' configuration map (interface => tag) earns that
 * tag at boot, and a class carrying a #[Tag('...')] attribute earns the
 * attribute's tags. One tag, one source: a tag the declaration spells
 * AND a rule or attribute would also earn is a boot refusal naming
 * every source. A typed variadic constructor parameter (Handler
 * ...$handlers) gathers every declared implementation of the type --
 * a scalar variadic stays empty, a union one is refused.
 *
 * The whole map is validated fail-closed before a single definition is
 * installed: unknown shape keys, unknown service references and circular
 * dependency chains halt the boot with the offending entry named. The
 * class shapes are also read once against their constructors -- an
 * uninstantiable class or a misfit arity is a boot-time refusal, never
 * a lazy reflection surprise at the first resolution.
 *
 * The shipped profile declares no services -- the framework ships none of
 * its own -- which boots a valid empty container; an application fills
 * this file with the services it wants constructed from configuration.
 */

return [];
