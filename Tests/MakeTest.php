<?php

declare(strict_types=1);

namespace Tests\Temant\Container;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\NotFoundException;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\ServiceWithNamedParam;

final class MakeTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function makeAlwaysCreatesNewInstance(): void
    {
        $this->c->set(Foo::class, fn() => new Foo());

        $a = $this->c->make(Foo::class);
        $b = $this->c->make(Foo::class);

        self::assertInstanceOf(Foo::class, $a);
        self::assertNotSame($a, $b, 'make() should always create new instances');
    }

    #[Test]
    public function makeDoesNotCacheResult(): void
    {
        $this->c->set(Foo::class, fn() => new Foo());

        $made = $this->c->make(Foo::class);
        $got = $this->c->get(Foo::class);
        $gotAgain = $this->c->get(Foo::class);

        self::assertNotSame($made, $got, 'make() result should not be cached');
        self::assertSame($got, $gotAgain, 'get() should still return cached singleton');
    }

    #[Test]
    public function makeWithParameterOverrides(): void
    {
        /** @var ServiceWithNamedParam $obj */
        $obj = $this->c->make(ServiceWithNamedParam::class, [
            'name' => 'Hello',
            'value' => 99,
        ]);

        self::assertInstanceOf(ServiceWithNamedParam::class, $obj);
        self::assertSame('Hello', $obj->name);
        self::assertSame(99, $obj->value);
        self::assertInstanceOf(Foo::class, $obj->foo);
    }

    #[Test]
    public function makeAutowiresWhenNoExplicitRegistration(): void
    {
        $obj = $this->c->make(Foo::class);

        self::assertInstanceOf(Foo::class, $obj);
    }

    #[Test]
    public function makeThrowsNotFoundForUnresolvable(): void
    {
        $this->c->setAutowiring(false);

        $this->expectException(NotFoundException::class);

        $this->c->make('non.existing.service');
    }

    #[Test]
    public function makeRespectsBindings(): void
    {
        $this->c->bind('my.foo', Foo::class);

        $obj = $this->c->make('my.foo');

        self::assertInstanceOf(Foo::class, $obj);
    }
}
