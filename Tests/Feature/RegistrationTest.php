<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\ContainerException;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\SomeClass;

final class RegistrationTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function setIsSharedAndSingletonIsItsAlias(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->singleton(Bar::class, fn(): Bar => new Bar());

        self::assertSame($this->c->get(Foo::class), $this->c->get(Foo::class));
        self::assertSame($this->c->get(Bar::class), $this->c->get(Bar::class));
    }

    #[Test]
    public function factoryReturnsAFreshInstanceEachTime(): void
    {
        $this->c->factory(Foo::class, fn(): Foo => new Foo());

        self::assertNotSame($this->c->get(Foo::class), $this->c->get(Foo::class));
    }

    #[Test]
    public function instanceReturnsTheExactObject(): void
    {
        $foo = new Foo();
        $this->c->instance(Foo::class, $foo);

        self::assertSame($foo, $this->c->get(Foo::class));
    }

    #[Test]
    public function multiRegistersSeveralSharedServices(): void
    {
        $this->c->multi([
            Foo::class => fn(): Foo => new Foo(),
            SomeClass::class => fn(): SomeClass => new SomeClass(),
        ]);

        self::assertInstanceOf(Foo::class, $this->c->get(Foo::class));
        self::assertInstanceOf(SomeClass::class, $this->c->get(SomeClass::class));
    }

    /**
     * @return iterable<string, array{callable(Container): mixed, callable(Container): mixed}>
     */
    public static function duplicateRegistrationPairs(): iterable
    {
        $factory = fn(): Foo => new Foo();

        yield 'shared then shared' => [
            fn(Container $c) => $c->set(Foo::class, $factory),
            fn(Container $c) => $c->set(Foo::class, $factory),
        ];
        yield 'shared then factory' => [
            fn(Container $c) => $c->set(Foo::class, $factory),
            fn(Container $c) => $c->factory(Foo::class, $factory),
        ];
        yield 'factory then shared' => [
            fn(Container $c) => $c->factory(Foo::class, $factory),
            fn(Container $c) => $c->set(Foo::class, $factory),
        ];
        yield 'instance then shared' => [
            fn(Container $c) => $c->instance(Foo::class, new Foo()),
            fn(Container $c) => $c->set(Foo::class, $factory),
        ];
    }

    /**
     * @param callable(Container): mixed $first
     * @param callable(Container): mixed $second
     */
    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('duplicateRegistrationPairs')]
    public function duplicateRegistrationIsRejected(callable $first, callable $second): void
    {
        $first($this->c);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/already registered/');

        $second($this->c);
    }

    #[Test]
    public function conditionalRegistrationSkipsWhenAlreadyBound(): void
    {
        $original = new Foo();
        $this->c->instance(Foo::class, $original);

        $this->c->setIf(Foo::class, fn(): Foo => new Foo());
        $this->c->singletonIf(Foo::class, fn(): Foo => new Foo());
        $this->c->factoryIf(Foo::class, fn(): Foo => new Foo());
        $this->c->instanceIf(Foo::class, new Foo());

        self::assertSame($original, $this->c->get(Foo::class));
    }

    #[Test]
    public function conditionalRegistrationAppliesWhenFree(): void
    {
        $this->c->setIf(Foo::class, fn(): Foo => new Foo());
        $this->c->singletonIf(Bar::class, fn(): Bar => new Bar());
        $this->c->instanceIf(SomeClass::class, new SomeClass());
        $this->c->factoryIf('rid', fn(): SomeClass => new SomeClass());

        self::assertSame($this->c->get(Foo::class), $this->c->get(Foo::class));
        self::assertInstanceOf(Bar::class, $this->c->get(Bar::class));
        self::assertInstanceOf(SomeClass::class, $this->c->get(SomeClass::class));
        self::assertNotSame($this->c->get('rid'), $this->c->get('rid'));
    }

    #[Test]
    public function factoryThatDoesNotReturnAnObjectIsRejectedAtResolution(): void
    {
        $this->c->factory('bad', fn() => 42);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/must return an object/');

        $this->c->get('bad');
    }
}
