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
 * The class and factory shapes also accept a 'calls' key -- the
 * method-call injection lane: a positional list of entries, each the
 * map ['method' => 'setLogger', 'arguments' => [...]] with
 * 'arguments' optional (an absent key is the empty plan). Every call
 * is invoked on the built instance after construction, its arguments
 * resolved through the same reference grammar the constructor's use,
 * and its answer discarded -- a call is an injection, not a builder.
 * The key is the opt-in itself: a declaration without it pays none of
 * the lane's machinery. A class shape's methods are proven at boot
 * (a declared call naming a missing method is a boot refusal); a
 * factory's product type is unknown at boot, so its calls are proven
 * at first resolution. An alias and a value refuse the key.
 *
 * Beside the services map, the top-level 'contextual' configuration
 * key declares contextual bindings -- per-consumer dependency
 * overrides:
 *
 *   'contextual' => ['billing' => ['PaymentGateway' => 'gateway.stripe']]
 *
 * When the consumer 'billing' resolves the dependency it declares (a
 * ['service' => PaymentGateway] reference in its arguments or calls),
 * it receives 'gateway.stripe' instead -- while every other consumer
 * keeps the default binding. The rewrite flows through a synthetic
 * alias, so the boot graph's guarantees hold unchanged: a missing
 * target fails the unknown-service refusal, cycles and captive
 * lifetimes through the binding are refused exactly as a direct
 * reference's would be.
 *
 * The top-level 'decorators' configuration key declares the decoration
 * lane -- wrapper chains over declared services:
 *
 *   'decorators' => ['logger' => [
 *       ['class' => CachedLogger::class, 'arguments' => [['service' => '@inner'], 300]],
 *       ['factory' => 'LoggerDecorators::timing'],
 *   ]]
 *
 * The first entry is the outermost wrapper: the example answers
 * CachedLogger(TimingLogger(inner)). Each entry is one shape -- 'class'
 * or 'factory' -- whose 'arguments' follow the ordinary reference
 * grammar, with the reserved ['service' => '@inner'] naming the layer
 * the entry wraps; at least one argument must spell it (a decorator
 * that never sees the service it decorates is a rebinding, and the
 * boot refuses it as such). The apply is compile-time definition
 * surgery: the target is re-homed under '@decor:<id>:base', the
 * wrappers are ordinary definitions referencing the next layer inward,
 * and every layer carries the decorated declaration's lifetime -- so
 * the boot graph's guarantees hold unchanged, the install loop
 * installs the chain like any other declaration, and the AOT dump
 * exports it for free. The tags travel with the original identifier
 * (the tag locator answers the *decorated* instance), and the lane's
 * own '@decor:' namespace is reserved while the lane runs. All four
 * shapes may be decorated -- a class, a factory, an alias or a value:
 * decoration wraps whatever the identifier resolves. The whole lane is
 * bounded at 128 declared decorators across every target.
 *
 * The top-level 'parameters' configuration key declares the global
 * parameter namespace -- the injectable scalars a constructor
 * parameter reads through the #[Value] attribute:
 *
 *   'parameters' => ['db' => ['host' => 'localhost', 'port' => 3306]]
 *
 *   public function __construct(#[Value('db.host')] string $host) {}
 *
 * The value is materialised at boot as the synthetic '@value:db.host'
 * value service -- one shared instance, validated in the boot graph
 * like any dependency -- and the plan references it through the
 * ordinary service-reference grammar, so a #[Value] parameter costs
 * the hot path nothing new. Addresses are dot-separated segments
 * ([A-Za-z0-9_-] each, 190 characters at most); a list under an
 * address is the parameter's whole value. A missing address is a
 * boot refusal naming the way out (declare it, or drop the attribute
 * so the constructor's own default applies); the attribute never
 * combines with #[Inject]/#[Target] and never rides a variadic, a
 * class-typed or a union parameter. Environment values close by
 * construction: the configuration's own environment layer folds
 * APP_PARAMETERS__DB__HOST onto parameters.db.host, so a parameter
 * takes its value from the running process's environment without
 * any container machinery of its own.
 *
 * The reserved 'providers' key under 'parameters' declares parameter
 * providers -- values computed at the boot (compile) phase:
 *
 *   'parameters' => ['providers' => ['app.version' => VersionProvider::class]]
 *
 * Each entry names a ParameterProviderInterface class (constructed
 * with no arguments) whose value() answer becomes the parameter's
 * value -- a scalar, null or an array, never an object. The key is
 * always the machinery's own: a literal under it is refused. One
 * address, one source -- a literal and a provider on the same
 * address is a boot refusal naming both spellings.
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
