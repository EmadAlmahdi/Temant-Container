<?php

declare(strict_types=1);

namespace Tests\Temant\Container;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\NotFoundException;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\SomeClass;

final class ChildContainerTest extends TestCase
{
    private Container $parent;

    protected function setUp(): void
    {
        $this->parent = new Container();
    }

    #[Test]
    public function childInheritsParentRegistrations(): void
    {
        $foo = new Foo();
        $this->parent->instance(Foo::class, $foo);

        $child = $this->parent->createChild();

        self::assertSame($foo, $child->get(Foo::class));
    }

    #[Test]
    public function childCanOverrideParentRegistrations(): void
    {
        $this->parent->set(Foo::class, fn() => new Foo());

        $child = $this->parent->createChild();
        $childFoo = new Foo();
        $child->instance(Foo::class, $childFoo);

        self::assertSame($childFoo, $child->get(Foo::class));
        self::assertNotSame($childFoo, $this->parent->get(Foo::class));
    }

    #[Test]
    public function childDoesNotAffectParent(): void
    {
        $child = $this->parent->createChild();
        $child->set(Bar::class, fn() => new Bar());

        self::assertTrue($child->has(Bar::class));

        // Parent should not have the child's registration (bar is autowirable, so disable)
        $this->parent->setAutowiring(false);
        self::assertFalse($this->parent->has(Bar::class));
    }

    #[Test]
    public function childHasReturnsTrueForParentEntries(): void
    {
        $this->parent->set(Foo::class, fn() => new Foo());

        $child = $this->parent->createChild();

        self::assertTrue($child->has(Foo::class));
    }

    #[Test]
    public function getParentReturnsParentContainer(): void
    {
        $child = $this->parent->createChild();

        self::assertSame($this->parent, $child->getParent());
    }

    #[Test]
    public function rootContainerHasNullParent(): void
    {
        self::assertNull($this->parent->getParent());
    }

    #[Test]
    public function childCanCreateGrandchild(): void
    {
        $this->parent->instance(Foo::class, new Foo());

        $child = $this->parent->createChild();
        $grandchild = $child->createChild();

        self::assertInstanceOf(Foo::class, $grandchild->get(Foo::class));
        self::assertSame($this->parent, $grandchild->getParent()?->getParent());
    }

    #[Test]
    public function makeOnChildDelegatesToParent(): void
    {
        $this->parent->set(Foo::class, fn() => new Foo());

        $child = $this->parent->createChild();
        $obj = $child->make(Foo::class);

        self::assertInstanceOf(Foo::class, $obj);
    }
}
