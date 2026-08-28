<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\HeavyService;
use Tests\Temant\Container\Fixtures\SomeClass;

final class IntrospectionTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function keysAndSnapshotsExposeRegistrations(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->factory(Bar::class, fn(): Bar => new Bar());
        $this->c->instance(SomeClass::class, new SomeClass());
        $this->c->alias('alias.foo', Foo::class);

        self::assertEqualsCanonicalizing(
            [Foo::class, Bar::class, SomeClass::class],
            $this->c->keys(),
        );

        $all = $this->c->all();
        self::assertArrayHasKey(Foo::class, $all['shared']);
        self::assertArrayHasKey(Bar::class, $all['factories']);
        self::assertArrayHasKey(SomeClass::class, $all['instances']);
        self::assertArrayHasKey('alias.foo', $all['bindings']);

        self::assertArrayHasKey(Foo::class, $this->c->allShared());
        self::assertArrayHasKey(Bar::class, $this->c->allFactories());
        self::assertArrayHasKey('alias.foo', $this->c->allBindings());
    }

    #[Test]
    public function getDefinitionDescribesAnEntryWithoutResolvingIt(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->tag(Foo::class, 'group');
        $this->c->extend(Foo::class, fn(object $o): object => $o);
        $this->c->bind('my.foo', Foo::class);

        $def = $this->c->getDefinition('my.foo');

        self::assertNotNull($def);
        self::assertSame('my.foo', $def['id']);
        self::assertSame(Foo::class, $def['resolvedId']);
        self::assertSame(Foo::class, $def['binding']);
        self::assertSame('shared', $def['type']);
        self::assertContains('group', $def['tags']);
        self::assertTrue($def['hasExtenders']);

        self::assertNull($this->c->getDefinition('unregistered'));
    }

    #[Test]
    public function getDefinitionReportsLazyEntries(): void
    {
        $this->c->lazy(HeavyService::class, fn(): HeavyService => new HeavyService());

        $def = $this->c->getDefinition(HeavyService::class);

        self::assertNotNull($def);
        self::assertSame('lazy', $def['type']);
    }
}
