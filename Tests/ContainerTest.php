<?php

declare(strict_types=1);

namespace Tests\Temant\Container;

use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\ContainerException;
use Temant\Container\Exception\NotFoundException;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\CallTarget;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\SomeClass;

final class ContainerTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container(true);
    }

    public function testGetThrowsNotFoundExceptionIfIdNotRegisteredAndAutowiringDisabled(): void
    {
        $this->c->setAutowiring(false);
        $this->expectException(NotFoundException::class);
        $this->c->get('non.existing.id');
    }

    public function testMultiRegistersManySharedServices(): void
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

    public function testSetIsSharedByDefault(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        $a = $this->c->get(Foo::class);
        $b = $this->c->get(Foo::class);

        self::assertSame($a, $b);
    }

    public function testSetThrowsIfAlreadyRegistered(): void
    {
        $this->expectException(ContainerException::class);
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->set(Foo::class, fn(): Foo => new Foo());
    }

    public function testSingletonIsAliasToSet(): void
    {
        $this->c->singleton(Foo::class, fn(): Foo => new Foo());

        $a = $this->c->get(Foo::class);
        $b = $this->c->get(Foo::class);

        self::assertSame($a, $b);
    }

    public function testFactoryReturnsNewInstanceEachTime(): void
    {
        $this->c->factory(Foo::class, fn(): Foo => new Foo());

        $a = $this->c->get(Foo::class);
        $b = $this->c->get(Foo::class);

        self::assertNotSame($a, $b);
    }

    public function testFactoryThrowsIfAlreadyRegistered(): void
    {
        $this->expectException(ContainerException::class);
        $this->c->factory(Foo::class, fn(): Foo => new Foo());
        $this->c->factory(Foo::class, fn(): Foo => new Foo());
    }

    public function testInstanceReturnsSameRegisteredObject(): void
    {
        $foo = new Foo();
        $this->c->instance(Foo::class, $foo);

        self::assertSame($foo, $this->c->get(Foo::class));
    }

    public function testBindResolvesAbstractToTarget(): void
    {
        // bind "abstract id" to Foo::class
        $this->c->bind('my.foo', Foo::class);

        // register Foo normally
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        $obj = $this->c->get('my.foo');
        self::assertInstanceOf(Foo::class, $obj);
    }

    public function testAliasIsBind(): void
    {
        $this->c->alias('alias.foo', Foo::class);
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        self::assertInstanceOf(Foo::class, $this->c->get('alias.foo'));
    }

    public function testHasRespectsBindings(): void
    {
        $this->c->alias('alias.foo', Foo::class);
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        self::assertTrue($this->c->has('alias.foo'));
    }

    public function testTagAndTaggedReturnInstances(): void
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

    public function testGetReturnsCachedInstanceWhenPresent(): void
    {
        $foo = new Foo();
        $this->c->instance(Foo::class, $foo);

        self::assertSame($foo, $this->c->get(Foo::class));
    }

    public function testGetSharedFactoryMustReturnObjectElseThrowsContainerException(): void
    {
        $this->c->set('bad.shared', fn() => 'not-an-object');

        $this->expectException(ContainerException::class);
        $this->c->get('bad.shared');
    }

    public function testGetFactoryMustReturnObjectElseThrowsContainerException(): void
    {
        $this->c->factory('bad.factory', fn() => 'not-an-object');

        $this->expectException(ContainerException::class);
        $this->c->get('bad.factory');
    }

    public function testRemoveRemovesEntry(): void
    {
        // shared
        $this->c->set(SomeClass::class, fn() => new SomeClass());
        $this->c->remove(SomeClass::class);
        self::assertFalse($this->c->has(SomeClass::class));

        // factory
        $this->c->factory(SomeClass::class, fn() => new SomeClass());
        $this->c->remove(SomeClass::class);
        self::assertFalse($this->c->has(SomeClass::class));

        // instance
        $this->c->instance(SomeClass::class, new SomeClass());
        $this->c->remove(SomeClass::class);
        self::assertFalse($this->c->has(SomeClass::class));

        // Bind/alias
        $this->c->alias('alias.some', SomeClass::class);
        $this->c->remove('alias.some');
        self::assertFalse($this->c->has('alias.some'));
    }

    public function testRemoveNonExistingThrowsContainerException(): void
    {
        $this->expectException(ContainerException::class);
        $this->c->remove(SomeClass::class);
    }

    public function testClearRemovesEverything(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->factory(Bar::class, fn(): Bar => new Bar());
        $this->c->alias('alias.foo', Foo::class);
        $this->c->tag(Foo::class, 'tag');

        $this->c->clear();

        self::assertFalse($this->c->has(Foo::class));
        self::assertFalse($this->c->has(Bar::class));
        self::assertSame([], $this->c->all());
        self::assertSame([], $this->c->allShared());
        self::assertSame([], $this->c->allFactories());
    }

    public function testFlushInstancesClearsCacheButKeepsDefinitions(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        $a = $this->c->get(Foo::class);
        $this->c->flushInstances();
        $b = $this->c->get(Foo::class);

        self::assertNotSame($a, $b, 'After flushInstances, shared instance should be recreated.');
        self::assertTrue($this->c->has(Foo::class));
    }

    public function testSetAutowiringAndHasAutowiring(): void
    {
        $this->c->setAutowiring(false);
        self::assertFalse($this->c->hasAutowiring());

        $this->c->setAutowiring(true);
        self::assertTrue($this->c->hasAutowiring());
    }

    public function testAllSharedAndAllFactoriesExposeRegistrations(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->factory(Bar::class, fn(): Bar => new Bar());

        $shared = $this->c->allShared();
        $factories = $this->c->allFactories();

        self::assertArrayHasKey(Foo::class, $shared);
        self::assertArrayHasKey(Bar::class, $factories);
    }

    public function testCallDelegatesToResolverAndSupportsNamedOverrides(): void
    {
        $target = new CallTarget();

        $result = $this->c->call([$target, 'method'], ['name' => 'override']);

        self::assertSame(SomeClass::class . ':override', $result);
    }

    public function testBindingLoopsThrowContainerException(): void
    {
        $this->c->bind('a', 'b');
        $this->c->bind('b', 'c');
        $this->c->bind('c', 'a');

        $this->expectException(ContainerException::class);
        $this->c->get('a');
    }
}