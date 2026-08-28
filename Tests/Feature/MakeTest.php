<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

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
    public function alwaysReturnsAFreshInstanceWithoutTouchingTheCache(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        $made = $this->c->make(Foo::class);
        $got = $this->c->get(Foo::class);

        self::assertNotSame($made, $got);
        self::assertSame($got, $this->c->get(Foo::class));
    }

    #[Test]
    public function appliesConstructorOverridesWhenAutowiring(): void
    {
        /** @var ServiceWithNamedParam $service */
        $service = $this->c->make(ServiceWithNamedParam::class, ['name' => 'Hello', 'value' => 99]);

        self::assertSame('Hello', $service->name);
        self::assertSame(99, $service->value);
        self::assertInstanceOf(Foo::class, $service->foo);
    }

    #[Test]
    public function respectsBindings(): void
    {
        $this->c->bind('my.foo', Foo::class);

        self::assertInstanceOf(Foo::class, $this->c->make('my.foo'));
    }

    #[Test]
    public function throwsNotFoundForUnresolvableIds(): void
    {
        $this->c->setAutowiring(false);

        $this->expectException(NotFoundException::class);

        $this->c->make('missing');
    }
}
