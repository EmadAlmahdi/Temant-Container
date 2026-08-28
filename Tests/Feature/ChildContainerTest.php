<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\Foo;

final class ChildContainerTest extends TestCase
{
    private Container $parent;

    protected function setUp(): void
    {
        $this->parent = new Container();
    }

    #[Test]
    public function childInheritsAndCanOverrideWithoutAffectingTheParent(): void
    {
        $this->parent->set(Foo::class, fn(): Foo => new Foo());

        $child = $this->parent->createChild();
        $childFoo = new Foo();
        $child->instance(Foo::class, $childFoo);

        self::assertSame($childFoo, $child->get(Foo::class));
        self::assertNotSame($childFoo, $this->parent->get(Foo::class));
    }

    #[Test]
    public function unresolvedEntriesFallBackToTheParent(): void
    {
        $this->parent->set(Foo::class, fn(): Foo => new Foo());
        $child = $this->parent->createChild();

        self::assertTrue($child->has(Foo::class));
        self::assertInstanceOf(Foo::class, $child->get(Foo::class));
        self::assertInstanceOf(Foo::class, $child->make(Foo::class));
    }

    #[Test]
    public function childRegistrationsDoNotLeakToTheParent(): void
    {
        $child = $this->parent->createChild();
        $child->set(Bar::class, fn(): Bar => new Bar());

        $this->parent->setAutowiring(false);

        self::assertTrue($child->has(Bar::class));
        self::assertFalse($this->parent->has(Bar::class));
    }

    #[Test]
    public function hierarchyIsNavigable(): void
    {
        $child = $this->parent->createChild();
        $grandchild = $child->createChild();

        self::assertNull($this->parent->getParent());
        self::assertSame($this->parent, $child->getParent());
        self::assertSame($this->parent, $grandchild->getParent()?->getParent());
    }
}
