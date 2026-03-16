<?php

declare(strict_types=1);

namespace Tests\Temant\Container;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\SomeClass;

final class ConditionalRegistrationTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function setIfRegistersWhenNotAlreadyRegistered(): void
    {
        $this->c->setIf(Foo::class, fn() => new Foo());

        self::assertTrue($this->c->has(Foo::class));
        self::assertInstanceOf(Foo::class, $this->c->get(Foo::class));
    }

    #[Test]
    public function setIfSkipsWhenAlreadyRegistered(): void
    {
        $original = new Foo();
        $this->c->instance(Foo::class, $original);

        $this->c->setIf(Foo::class, fn() => new Foo());

        self::assertSame($original, $this->c->get(Foo::class));
    }

    #[Test]
    public function singletonIfIsAliasForSetIf(): void
    {
        $this->c->singletonIf(Foo::class, fn() => new Foo());

        self::assertTrue($this->c->has(Foo::class));
    }

    #[Test]
    public function factoryIfRegistersWhenNotAlreadyRegistered(): void
    {
        $this->c->factoryIf(Foo::class, fn() => new Foo());

        $a = $this->c->get(Foo::class);
        $b = $this->c->get(Foo::class);

        self::assertNotSame($a, $b, 'factoryIf should register a factory');
    }

    #[Test]
    public function factoryIfSkipsWhenAlreadyRegistered(): void
    {
        $this->c->set(Foo::class, fn() => new Foo());

        // Should not throw (duplicate detection skipped)
        $this->c->factoryIf(Foo::class, fn() => new Foo());

        // Original registration (shared) should still be in effect
        $a = $this->c->get(Foo::class);
        $b = $this->c->get(Foo::class);
        self::assertSame($a, $b, 'Original shared registration should be preserved');
    }

    #[Test]
    public function instanceIfRegistersWhenNotAlreadyRegistered(): void
    {
        $foo = new Foo();
        $this->c->instanceIf(Foo::class, $foo);

        self::assertSame($foo, $this->c->get(Foo::class));
    }

    #[Test]
    public function instanceIfSkipsWhenAlreadyRegistered(): void
    {
        $original = new Foo();
        $this->c->instance(Foo::class, $original);

        $this->c->instanceIf(Foo::class, new Foo());

        self::assertSame($original, $this->c->get(Foo::class));
    }
}
