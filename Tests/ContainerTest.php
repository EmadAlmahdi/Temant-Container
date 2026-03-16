<?php

declare(strict_types=1);

namespace Tests\Temant\Container;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Temant\Container\Container;
use Temant\Container\ContainerInterface;
use Temant\Container\Exception\ContainerException;
use Temant\Container\Exception\NotFoundException;
use Temant\Container\ServiceProviderInterface;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\CallTarget;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\SomeClass;

final class ContainerTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    // -------------------------------------------------------------------------
    // PSR-11 Contract
    // -------------------------------------------------------------------------

    #[Test]
    public function implementsPsrContainerInterface(): void
    {
        self::assertInstanceOf(\Psr\Container\ContainerInterface::class, $this->c);
        self::assertInstanceOf(ContainerInterface::class, $this->c);
    }

    #[Test]
    public function getThrowsNotFoundExceptionForUnknownIdWithAutowiringDisabled(): void
    {
        $this->c->setAutowiring(false);

        $this->expectException(NotFoundException::class);
        $this->expectException(NotFoundExceptionInterface::class);

        $this->c->get('non.existing.id');
    }

    #[Test]
    public function containerExceptionsImplementPsr11Interface(): void
    {
        $this->c->set('bad', fn() => 'not-an-object');

        try {
            $this->c->get('bad');
            self::fail('Expected ContainerException');
        } catch (ContainerExceptionInterface $e) {
            self::assertInstanceOf(ContainerException::class, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Shared (Singleton) Registration
    // -------------------------------------------------------------------------

    #[Test]
    public function setIsSharedByDefault(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        $a = $this->c->get(Foo::class);
        $b = $this->c->get(Foo::class);

        self::assertSame($a, $b);
    }

    #[Test]
    public function setThrowsIfAlreadyRegistered(): void
    {
        $this->expectException(ContainerException::class);

        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->set(Foo::class, fn(): Foo => new Foo());
    }

    #[Test]
    public function singletonIsAliasForSet(): void
    {
        $this->c->singleton(Foo::class, fn(): Foo => new Foo());

        $a = $this->c->get(Foo::class);
        $b = $this->c->get(Foo::class);

        self::assertSame($a, $b);
    }

    #[Test]
    public function multiRegistersManySharedServices(): void
    {
        $this->c->multi([
            Foo::class => fn(): Foo => new Foo(),
            SomeClass::class => fn(): SomeClass => new SomeClass(),
        ]);

        self::assertTrue($this->c->has(Foo::class));
        self::assertTrue($this->c->has(SomeClass::class));
        self::assertInstanceOf(Foo::class, $this->c->get(Foo::class));
        self::assertInstanceOf(SomeClass::class, $this->c->get(SomeClass::class));
    }

    #[Test]
    public function sharedFactoryMustReturnObject(): void
    {
        $this->c->set('bad.shared', fn() => 'not-an-object');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/must return an object/');

        $this->c->get('bad.shared');
    }

    // -------------------------------------------------------------------------
    // Factory Registration
    // -------------------------------------------------------------------------

    #[Test]
    public function factoryReturnsNewInstanceEachTime(): void
    {
        $this->c->factory(Foo::class, fn(): Foo => new Foo());

        $a = $this->c->get(Foo::class);
        $b = $this->c->get(Foo::class);

        self::assertNotSame($a, $b);
    }

    #[Test]
    public function factoryThrowsIfAlreadyRegistered(): void
    {
        $this->expectException(ContainerException::class);

        $this->c->factory(Foo::class, fn(): Foo => new Foo());
        $this->c->factory(Foo::class, fn(): Foo => new Foo());
    }

    #[Test]
    public function factoryMustReturnObject(): void
    {
        $this->c->factory('bad.factory', fn() => 42);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/must return an object/');

        $this->c->get('bad.factory');
    }

    // -------------------------------------------------------------------------
    // Instance Registration
    // -------------------------------------------------------------------------

    #[Test]
    public function instanceReturnsSameRegisteredObject(): void
    {
        $foo = new Foo();
        $this->c->instance(Foo::class, $foo);

        self::assertSame($foo, $this->c->get(Foo::class));
    }

    #[Test]
    public function instanceThrowsIfAlreadyRegistered(): void
    {
        $this->expectException(ContainerException::class);

        $this->c->instance(Foo::class, new Foo());
        $this->c->instance(Foo::class, new Foo());
    }

    #[Test]
    public function instanceThrowsIfIdRegisteredAsShared(): void
    {
        $this->expectException(ContainerException::class);

        $this->c->set(Foo::class, fn() => new Foo());
        $this->c->instance(Foo::class, new Foo());
    }

    // -------------------------------------------------------------------------
    // Bindings / Aliases
    // -------------------------------------------------------------------------

    #[Test]
    public function bindResolvesAbstractToTarget(): void
    {
        $this->c->bind('my.foo', Foo::class);
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        $obj = $this->c->get('my.foo');

        self::assertInstanceOf(Foo::class, $obj);
    }

    #[Test]
    public function aliasIsBind(): void
    {
        $this->c->alias('alias.foo', Foo::class);
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        self::assertInstanceOf(Foo::class, $this->c->get('alias.foo'));
    }

    #[Test]
    public function hasRespectsBindings(): void
    {
        $this->c->alias('alias.foo', Foo::class);
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        self::assertTrue($this->c->has('alias.foo'));
    }

    #[Test]
    public function chainedBindingsResolveCorrectly(): void
    {
        $this->c->bind('a', 'b');
        $this->c->bind('b', Foo::class);
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        self::assertInstanceOf(Foo::class, $this->c->get('a'));
    }

    #[Test]
    public function circularBindingLoopThrowsContainerException(): void
    {
        $this->c->bind('a', 'b');
        $this->c->bind('b', 'c');
        $this->c->bind('c', 'a');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/Circular binding loop/');

        $this->c->get('a');
    }

    // -------------------------------------------------------------------------
    // Tagging
    // -------------------------------------------------------------------------

    #[Test]
    public function tagAndTaggedReturnInstances(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->factory(Bar::class, fn(): Bar => new Bar());

        $this->c->tag(Foo::class, 'group');
        $this->c->tag(Bar::class, 'group');

        $items = $this->c->tagged('group');

        self::assertCount(2, $items);
        self::assertInstanceOf(Foo::class, $items[0]);
        self::assertInstanceOf(Bar::class, $items[1]);
    }

    #[Test]
    public function taggedReturnsEmptyArrayForUnknownTag(): void
    {
        self::assertSame([], $this->c->tagged('nonexistent'));
    }

    // -------------------------------------------------------------------------
    // Extend / Decoration
    // -------------------------------------------------------------------------

    #[Test]
    public function extendDecoratesExistingSharedService(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        $wrapper = new class extends Foo {
            public Foo $inner;
        };

        $this->c->extend(Foo::class, function (object $service, ContainerInterface $c) use ($wrapper): object {
            $wrapper->inner = $service;
            return $wrapper;
        });

        $resolved = $this->c->get(Foo::class);

        self::assertSame($wrapper, $resolved);
        self::assertInstanceOf(Foo::class, $wrapper->inner);
    }

    #[Test]
    public function extendDecoratesFactoryService(): void
    {
        $calls = 0;

        $this->c->factory(Foo::class, function () use (&$calls): Foo {
            $calls++;
            return new Foo();
        });

        $this->c->extend(Foo::class, fn(object $service, ContainerInterface $c): object => $service);

        $this->c->get(Foo::class);
        $this->c->get(Foo::class);

        self::assertSame(2, $calls, 'Factory should be called each time, not cached.');
    }

    #[Test]
    public function extendThrowsForUnknownId(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/Cannot extend/');

        $this->c->extend('unknown.id', fn(object $s, ContainerInterface $c): object => $s);
    }

    #[Test]
    public function multipleExtendersApplyInOrder(): void
    {
        $this->c->set('counter', fn(): \stdClass => (object) ['value' => 0]);

        $this->c->extend('counter', function (object $s): object {
            $s->value += 10;
            return $s;
        });

        $this->c->extend('counter', function (object $s): object {
            $s->value *= 2;
            return $s;
        });

        $result = $this->c->get('counter');

        self::assertSame(20, $result->value);
    }

    // -------------------------------------------------------------------------
    // Service Providers
    // -------------------------------------------------------------------------

    #[Test]
    public function serviceProviderRegisterAndBoot(): void
    {
        $registered = false;
        $booted = false;

        $provider = new class ($registered, $booted) implements ServiceProviderInterface {
            public function __construct(private bool &$registered, private bool &$booted) {}

            public function register(Container $container): void
            {
                $this->registered = true;
                $container->set(Foo::class, fn() => new Foo());
            }

            public function boot(Container $container): void
            {
                $this->booted = true;
            }
        };

        $this->c->register($provider);

        self::assertTrue($registered, 'register() should be called immediately.');
        self::assertFalse($booted, 'boot() should not be called until boot() is invoked.');
        self::assertTrue($this->c->has(Foo::class));

        $this->c->boot();

        self::assertTrue($booted, 'boot() should be called after Container::boot().');
    }

    #[Test]
    public function lateProviderIsBootedImmediatelyIfContainerAlreadyBooted(): void
    {
        $this->c->boot();

        $booted = false;

        $provider = new class ($booted) implements ServiceProviderInterface {
            public function __construct(private bool &$booted) {}
            public function register(Container $container): void {}
            public function boot(Container $container): void { $this->booted = true; }
        };

        $this->c->register($provider);

        self::assertTrue($booted, 'Late provider should be booted immediately.');
    }

    #[Test]
    public function bootIsIdempotent(): void
    {
        $count = 0;

        $provider = new class ($count) implements ServiceProviderInterface {
            public function __construct(private int &$count) {}
            public function register(Container $container): void {}
            public function boot(Container $container): void { $this->count++; }
        };

        $this->c->register($provider);
        $this->c->boot();
        $this->c->boot();
        $this->c->boot();

        self::assertSame(1, $count, 'boot() should only call providers once.');
    }

    // -------------------------------------------------------------------------
    // Autowiring
    // -------------------------------------------------------------------------

    #[Test]
    public function autowiringResolvesUnregisteredClass(): void
    {
        $obj = $this->c->get(Foo::class);

        self::assertInstanceOf(Foo::class, $obj);
    }

    #[Test]
    public function autowiringCachesInstanceByDefault(): void
    {
        $a = $this->c->get(Foo::class);
        $b = $this->c->get(Foo::class);

        self::assertSame($a, $b);
    }

    #[Test]
    public function autowiringDoesNotCacheWhenDisabled(): void
    {
        $c = new Container(autowiringEnabled: true, cacheAutowire: false);

        $a = $c->get(Foo::class);
        $b = $c->get(Foo::class);

        self::assertNotSame($a, $b);
    }

    #[Test]
    public function setAutowiringFalseAtRuntimePreventsAutowiring(): void
    {
        $this->c->setAutowiring(false);

        $this->expectException(NotFoundException::class);

        $this->c->get(Foo::class);
    }

    #[Test]
    public function hasReturnsTrueForAutowirableClass(): void
    {
        self::assertTrue($this->c->has(Foo::class));
    }

    #[Test]
    public function hasReturnsFalseForAutowirableClassWhenAutowiringDisabled(): void
    {
        $this->c->setAutowiring(false);

        self::assertFalse($this->c->has(Foo::class));
    }

    #[Test]
    public function setAutowiringAndHasAutowiring(): void
    {
        $this->c->setAutowiring(false);
        self::assertFalse($this->c->hasAutowiring());

        $this->c->setAutowiring(true);
        self::assertTrue($this->c->hasAutowiring());
    }

    // -------------------------------------------------------------------------
    // Callable Invocation
    // -------------------------------------------------------------------------

    #[Test]
    public function callDelegatesToResolverAndSupportsNamedOverrides(): void
    {
        $target = new CallTarget();

        $result = $this->c->call([$target, 'method'], ['name' => 'override']);

        self::assertSame(SomeClass::class . ':override', $result);
    }

    #[Test]
    public function callResolvesClosureParameters(): void
    {
        $result = $this->c->call(fn(Foo $foo): string => $foo::class);

        self::assertSame(Foo::class, $result);
    }

    // -------------------------------------------------------------------------
    // Removal / Reset
    // -------------------------------------------------------------------------

    #[Test]
    public function removeRemovesSharedEntry(): void
    {
        $this->c->set(SomeClass::class, fn() => new SomeClass());
        $this->c->remove(SomeClass::class);

        self::assertNotContains(SomeClass::class, $this->c->keys());
        self::assertArrayNotHasKey(SomeClass::class, $this->c->allShared());
    }

    #[Test]
    public function removeRemovesFactoryEntry(): void
    {
        $this->c->factory(SomeClass::class, fn() => new SomeClass());
        $this->c->remove(SomeClass::class);

        // has() may still return true due to autowiring, so check keys()
        self::assertNotContains(SomeClass::class, $this->c->keys());
    }

    #[Test]
    public function removeRemovesInstanceEntry(): void
    {
        $this->c->instance(SomeClass::class, new SomeClass());
        $this->c->remove(SomeClass::class);

        self::assertEmpty($this->c->allInstances());
    }

    #[Test]
    public function removeRemovesBindingEntry(): void
    {
        $this->c->alias('alias.some', SomeClass::class);
        $this->c->remove('alias.some');

        self::assertArrayNotHasKey('alias.some', $this->c->allBindings());
    }

    #[Test]
    public function removeNonExistingThrowsContainerException(): void
    {
        $this->expectException(ContainerException::class);
        $this->c->remove('totally.unknown.and.not.registered');
    }

    #[Test]
    public function clearRemovesEverything(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->factory(Bar::class, fn(): Bar => new Bar());
        $this->c->alias('alias.foo', Foo::class);
        $this->c->tag(Foo::class, 'tag');

        $this->c->clear();

        self::assertEmpty($this->c->allShared());
        self::assertEmpty($this->c->allFactories());
        self::assertEmpty($this->c->allInstances());
        self::assertEmpty($this->c->allBindings());
        self::assertEmpty($this->c->keys());
    }

    #[Test]
    public function flushInstancesClearsCacheButKeepsDefinitions(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        $a = $this->c->get(Foo::class);
        $this->c->flushInstances();
        $b = $this->c->get(Foo::class);

        self::assertNotSame($a, $b, 'After flushInstances, shared instance should be recreated.');
        self::assertTrue($this->c->has(Foo::class));
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    #[Test]
    public function keysReturnsAllRegisteredIds(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->factory(Bar::class, fn(): Bar => new Bar());
        $this->c->instance(SomeClass::class, new SomeClass());

        $keys = $this->c->keys();

        self::assertContains(Foo::class, $keys);
        self::assertContains(Bar::class, $keys);
        self::assertContains(SomeClass::class, $keys);
    }

    #[Test]
    public function allReturnsStructuredArray(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        $all = $this->c->all();

        self::assertArrayHasKey('shared', $all);
        self::assertArrayHasKey('factories', $all);
        self::assertArrayHasKey('instances', $all);
        self::assertArrayHasKey('bindings', $all);
        self::assertArrayHasKey('tags', $all);
        self::assertArrayHasKey(Foo::class, $all['shared']);
    }

    #[Test]
    public function allSharedAndAllFactoriesExposeRegistrations(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->factory(Bar::class, fn(): Bar => new Bar());

        self::assertArrayHasKey(Foo::class, $this->c->allShared());
        self::assertArrayHasKey(Bar::class, $this->c->allFactories());
    }

    // -------------------------------------------------------------------------
    // Cross-registration conflict detection
    // -------------------------------------------------------------------------

    #[Test]
    public function cannotRegisterFactoryIfSharedExists(): void
    {
        $this->expectException(ContainerException::class);

        $this->c->set(Foo::class, fn() => new Foo());
        $this->c->factory(Foo::class, fn() => new Foo());
    }

    #[Test]
    public function cannotRegisterSharedIfFactoryExists(): void
    {
        $this->expectException(ContainerException::class);

        $this->c->factory(Foo::class, fn() => new Foo());
        $this->c->set(Foo::class, fn() => new Foo());
    }

    #[Test]
    public function cannotRegisterSharedIfInstanceExists(): void
    {
        $this->expectException(ContainerException::class);

        $this->c->instance(Foo::class, new Foo());
        $this->c->set(Foo::class, fn() => new Foo());
    }
}
