<?php

declare(strict_types=1);

namespace Tests\Temant\Container;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\LazyProxy;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\SomeClass;

final class LazyProxyTest extends TestCase
{
    // -------------------------------------------------------------------------
    // LazyProxy class tests
    // -------------------------------------------------------------------------

    #[Test]
    public function proxyDefersInstantiation(): void
    {
        $created = false;

        $proxy = new LazyProxy(function () use (&$created): object {
            $created = true;
            return new \stdClass();
        });

        self::assertFalse($created, 'Factory should not run until first use');
        self::assertFalse($proxy->isInitialized());
    }

    #[Test]
    public function proxyCreatesInstanceOnMethodCall(): void
    {
        $inner = new \stdClass();
        $inner->value = 42;

        $proxy = new LazyProxy(fn(): object => $inner);

        self::assertFalse($proxy->isInitialized());
        self::assertSame(42, $proxy->value);
        self::assertTrue($proxy->isInitialized());
    }

    #[Test]
    public function proxyDelegatesPropertySet(): void
    {
        $inner = new \stdClass();
        $proxy = new LazyProxy(fn(): object => $inner);

        $proxy->name = 'test';

        self::assertSame('test', $inner->name);
    }

    #[Test]
    public function proxyDelegatesIsset(): void
    {
        $inner = new \stdClass();
        $inner->exists = true;

        $proxy = new LazyProxy(fn(): object => $inner);

        self::assertTrue(isset($proxy->exists));
        self::assertFalse(isset($proxy->missing));
    }

    #[Test]
    public function getTargetReturnsRealInstance(): void
    {
        $inner = new \stdClass();
        $proxy = new LazyProxy(fn(): object => $inner);

        self::assertSame($inner, $proxy->getTarget());
    }

    #[Test]
    public function factoryIsInvokedOnlyOnce(): void
    {
        $count = 0;

        $proxy = new LazyProxy(function () use (&$count): object {
            $count++;
            return new \stdClass();
        });

        $proxy->getTarget();
        $proxy->getTarget();

        self::assertSame(1, $count);
    }

    // -------------------------------------------------------------------------
    // Container::lazy() integration
    // -------------------------------------------------------------------------

    #[Test]
    public function containerLazyRegistersLazyProxy(): void
    {
        $c = new Container();
        $created = false;

        $c->lazy(Foo::class, function () use (&$created) {
            $created = true;
            return new Foo();
        });

        self::assertFalse($created, 'Factory should not run on registration');
        self::assertTrue($c->has(Foo::class));

        $proxy = $c->get(Foo::class);

        self::assertInstanceOf(LazyProxy::class, $proxy);
        self::assertFalse($created, 'Factory should not run on get()');
    }

    #[Test]
    public function lazyProxyReturnsSameProxyInstance(): void
    {
        $c = new Container();
        $c->lazy(Foo::class, fn() => new Foo());

        $a = $c->get(Foo::class);
        $b = $c->get(Foo::class);

        self::assertSame($a, $b, 'Should return same proxy instance');
    }
}
