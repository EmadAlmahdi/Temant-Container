<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\Foo;

final class EventsTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function idSpecificResolvingCallbackFiresOnlyForThatId(): void
    {
        $seen = [];
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->set(Bar::class, fn(): Bar => new Bar());
        $this->c->resolving(Foo::class, function () use (&$seen): void {
            $seen[] = 'foo';
        });

        $this->c->get(Bar::class);
        $this->c->get(Foo::class);

        self::assertSame(['foo'], $seen);
    }

    #[Test]
    public function globalCallbacksFireForEveryResolution(): void
    {
        $resolving = 0;
        $after = 0;
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->factory(Bar::class, fn(): Bar => new Bar());
        $this->c->resolving(function () use (&$resolving): void {
            $resolving++;
        });
        $this->c->afterResolving(function () use (&$after): void {
            $after++;
        });

        $this->c->get(Foo::class);
        $this->c->get(Bar::class);

        self::assertSame(2, $resolving);
        self::assertSame(2, $after);
    }

    #[Test]
    public function resolvingRunsBeforeAfterResolving(): void
    {
        $order = [];
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->afterResolving(Foo::class, function () use (&$order): void {
            $order[] = 'after';
        });
        $this->c->resolving(Foo::class, function () use (&$order): void {
            $order[] = 'resolving';
        });

        $this->c->get(Foo::class);

        self::assertSame(['resolving', 'after'], $order);
    }

    #[Test]
    public function callbacksDoNotFireOnCachedSingletonHits(): void
    {
        $count = 0;
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->resolving(Foo::class, function () use (&$count): void {
            $count++;
        });

        $this->c->get(Foo::class);
        $this->c->get(Foo::class);

        self::assertSame(1, $count);
    }
}
