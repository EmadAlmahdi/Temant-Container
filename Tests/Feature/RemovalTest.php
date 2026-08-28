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

final class RemovalTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function removeStripsDefinitionsBindingsAndExtenders(): void
    {
        $this->c->set(SomeClass::class, fn(): SomeClass => new SomeClass());
        $this->c->extend(SomeClass::class, fn(object $o): object => $o);
        $this->c->alias('alias.some', SomeClass::class);

        $this->c->remove(SomeClass::class);
        $this->c->remove('alias.some');

        self::assertNotContains(SomeClass::class, $this->c->keys());
        self::assertArrayNotHasKey('alias.some', $this->c->allBindings());
        self::assertFalse($this->c->getDefinition(SomeClass::class)['hasExtenders'] ?? false);
    }

    #[Test]
    public function removingSomethingThatIsNotThereThrows(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/no entry found/');

        $this->c->remove('never.registered');
    }

    #[Test]
    public function clearWipesEverything(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->factory(Bar::class, fn(): Bar => new Bar());
        $this->c->alias('alias.foo', Foo::class);
        $this->c->tag(Foo::class, 'tag');

        $this->c->clear();

        self::assertSame([], $this->c->keys());
        self::assertSame([], $this->c->allBindings());
        self::assertSame([], $this->c->tagged('tag'));
    }

    #[Test]
    public function flushInstancesKeepsDefinitionsButRebuildsSingletons(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        $a = $this->c->get(Foo::class);
        $this->c->flushInstances();
        $b = $this->c->get(Foo::class);

        self::assertNotSame($a, $b);
        self::assertTrue($this->c->has(Foo::class));
    }
}
