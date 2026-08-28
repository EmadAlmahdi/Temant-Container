<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\ContainerException;
use Temant\Container\Exception\FrozenContainerException;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\Foo;

final class FreezeAndWarmUpTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function freezeReportsStateAndStillAllowsResolution(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        self::assertFalse($this->c->isFrozen());
        $this->c->freeze();
        self::assertTrue($this->c->isFrozen());

        self::assertInstanceOf(Foo::class, $this->c->get(Foo::class));
    }

    #[Test]
    public function everyMutatorThrowsFrozenExceptionWhichIsAlsoAContainerException(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->freeze();

        $mutations = [
            fn() => $this->c->set('a', fn() => new Foo()),
            fn() => $this->c->factory('b', fn() => new Bar()),
            fn() => $this->c->instance('c', new Bar()),
            fn() => $this->c->lazy('d', fn() => new Foo()),
            fn() => $this->c->bind('x', 'y'),
            fn() => $this->c->tag('x', 'y'),
            fn() => $this->c->extend(Foo::class, fn($o) => $o),
            fn() => $this->c->inflect(Foo::class, fn() => null),
            fn() => $this->c->resolving(fn() => null),
            fn() => $this->c->afterResolving(fn() => null),
            fn() => $this->c->remove(Foo::class),
            fn() => $this->c->when('x')->needs('y')->give('z'),
        ];

        foreach ($mutations as $i => $mutation) {
            try {
                $mutation();
                self::fail("Mutation #{$i} should have thrown");
            } catch (FrozenContainerException $e) {
                self::assertInstanceOf(ContainerException::class, $e);
            }
        }
    }

    #[Test]
    public function clearLiftsTheFreeze(): void
    {
        $this->c->freeze();
        $this->c->clear();

        self::assertFalse($this->c->isFrozen());
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        self::assertTrue($this->c->has(Foo::class));
    }

    #[Test]
    public function warmUpResolvesEverySingletonOnceAndSkipsResolvedOnes(): void
    {
        $count = 0;
        $this->c->set('a', function () use (&$count): Foo {
            $count++;

            return new Foo();
        });
        $this->c->set('b', function () use (&$count): Bar {
            $count++;

            return new Bar();
        });

        $this->c->get('a');
        $this->c->warmUp();

        self::assertSame(2, $count);
        self::assertNotEmpty($this->c->allInstances());
    }
}
