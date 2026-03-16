<?php

declare(strict_types=1);

namespace Tests\Temant\Container;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\ContainerInterface;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\Foo;

final class ContainerEventsTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    // -------------------------------------------------------------------------
    // Resolving Callbacks
    // -------------------------------------------------------------------------

    #[Test]
    public function resolvingCallbackFiresForSpecificId(): void
    {
        $fired = false;

        $this->c->set(Foo::class, fn() => new Foo());
        $this->c->resolving(Foo::class, function (object $obj, ContainerInterface $c) use (&$fired): void {
            $fired = true;
            self::assertInstanceOf(Foo::class, $obj);
        });

        $this->c->get(Foo::class);

        self::assertTrue($fired);
    }

    #[Test]
    public function resolvingCallbackDoesNotFireForOtherId(): void
    {
        $fired = false;

        $this->c->set(Foo::class, fn() => new Foo());
        $this->c->set(Bar::class, fn() => new Bar());

        $this->c->resolving(Foo::class, function () use (&$fired): void {
            $fired = true;
        });

        $this->c->get(Bar::class);

        self::assertFalse($fired);
    }

    #[Test]
    public function globalResolvingCallbackFiresForAllResolutions(): void
    {
        $count = 0;

        $this->c->set(Foo::class, fn() => new Foo());
        $this->c->factory(Bar::class, fn() => new Bar());

        $this->c->resolving(function () use (&$count): void {
            $count++;
        });

        $this->c->get(Foo::class);
        $this->c->get(Bar::class);

        self::assertSame(2, $count);
    }

    // -------------------------------------------------------------------------
    // After-Resolving Callbacks
    // -------------------------------------------------------------------------

    #[Test]
    public function afterResolvingCallbackFiresForSpecificId(): void
    {
        $fired = false;

        $this->c->set(Foo::class, fn() => new Foo());
        $this->c->afterResolving(Foo::class, function (object $obj, ContainerInterface $c) use (&$fired): void {
            $fired = true;
            self::assertInstanceOf(Foo::class, $obj);
        });

        $this->c->get(Foo::class);

        self::assertTrue($fired);
    }

    #[Test]
    public function globalAfterResolvingCallbackFiresForAll(): void
    {
        $count = 0;

        $this->c->set(Foo::class, fn() => new Foo());
        $this->c->factory(Bar::class, fn() => new Bar());

        $this->c->afterResolving(function () use (&$count): void {
            $count++;
        });

        $this->c->get(Foo::class);
        $this->c->get(Bar::class);

        self::assertSame(2, $count);
    }

    // -------------------------------------------------------------------------
    // Callback ordering
    // -------------------------------------------------------------------------

    #[Test]
    public function callbacksFiringOrder(): void
    {
        $order = [];

        $this->c->set(Foo::class, fn() => new Foo());

        $this->c->resolving(Foo::class, function () use (&$order): void {
            $order[] = 'resolving';
        });

        $this->c->afterResolving(Foo::class, function () use (&$order): void {
            $order[] = 'afterResolving';
        });

        $this->c->get(Foo::class);

        self::assertSame(['resolving', 'afterResolving'], $order);
    }

    #[Test]
    public function callbacksDoNotFireForCachedInstances(): void
    {
        $count = 0;

        $this->c->set(Foo::class, fn() => new Foo());
        $this->c->resolving(Foo::class, function () use (&$count): void {
            $count++;
        });

        $this->c->get(Foo::class);
        $this->c->get(Foo::class); // Should hit cache, not fire callback

        self::assertSame(1, $count);
    }

    #[Test]
    public function callbacksFireOnFactoryEveryTime(): void
    {
        $count = 0;

        $this->c->factory(Foo::class, fn() => new Foo());
        $this->c->resolving(Foo::class, function () use (&$count): void {
            $count++;
        });

        $this->c->get(Foo::class);
        $this->c->get(Foo::class);

        self::assertSame(2, $count);
    }
}
