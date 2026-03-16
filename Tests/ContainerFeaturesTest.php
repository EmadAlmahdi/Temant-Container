<?php

declare(strict_types=1);

namespace Tests\Temant\Container;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\ContainerException;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\CallTarget;
use Tests\Temant\Container\Fixtures\ConsoleLogger;
use Tests\Temant\Container\Fixtures\ConstructorWithTypedVariadic;
use Tests\Temant\Container\Fixtures\FileLogger;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\LoggerInterface;
use Tests\Temant\Container\Fixtures\SomeClass;

final class ContainerFeaturesTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    // -------------------------------------------------------------------------
    // Class@method call() syntax
    // -------------------------------------------------------------------------

    #[Test]
    public function callWithClassAtMethodSyntax(): void
    {
        $result = $this->c->call(CallTarget::class . '@method', ['name' => 'test']);

        self::assertSame(SomeClass::class . ':test', $result);
    }

    #[Test]
    public function callWithClassAtMethodResolvesFromContainer(): void
    {
        $target = new CallTarget();
        $this->c->instance(CallTarget::class, $target);

        $result = $this->c->call(CallTarget::class . '@method', ['name' => 'hello']);

        self::assertSame(SomeClass::class . ':hello', $result);
    }

    // -------------------------------------------------------------------------
    // Variadic Resolution
    // -------------------------------------------------------------------------

    #[Test]
    public function variadicResolvedFromTaggedServices(): void
    {
        $this->c->set(FileLogger::class, fn() => new FileLogger());
        $this->c->set(ConsoleLogger::class, fn() => new ConsoleLogger());

        $this->c->tag(FileLogger::class, LoggerInterface::class);
        $this->c->tag(ConsoleLogger::class, LoggerInterface::class);

        /** @var ConstructorWithTypedVariadic $obj */
        $obj = $this->c->get(ConstructorWithTypedVariadic::class);

        self::assertCount(2, $obj->loggers);
        self::assertInstanceOf(FileLogger::class, $obj->loggers[0]);
        self::assertInstanceOf(ConsoleLogger::class, $obj->loggers[1]);
    }

    #[Test]
    public function variadicResolvesEmptyWhenNoTagsOrRegistrations(): void
    {
        // No LoggerInterface registered — variadic should resolve to empty
        $this->c->setAutowiring(false);

        $this->c->set(ConstructorWithTypedVariadic::class, fn($c) => new ConstructorWithTypedVariadic());

        /** @var ConstructorWithTypedVariadic $obj */
        $obj = $this->c->get(ConstructorWithTypedVariadic::class);

        self::assertCount(0, $obj->loggers);
    }

    #[Test]
    public function variadicResolvesSingleRegisteredInstance(): void
    {
        $this->c->set(LoggerInterface::class, fn() => new FileLogger());

        // LoggerInterface is registered but not tagged — should resolve one instance
        /** @var ConstructorWithTypedVariadic $obj */
        $obj = $this->c->get(ConstructorWithTypedVariadic::class);

        self::assertCount(1, $obj->loggers);
        self::assertInstanceOf(FileLogger::class, $obj->loggers[0]);
    }

    // -------------------------------------------------------------------------
    // Freeze / Warm-Up
    // -------------------------------------------------------------------------

    #[Test]
    public function freezePreventsModifications(): void
    {
        $this->c->freeze();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/frozen/');

        $this->c->set(Foo::class, fn() => new Foo());
    }

    #[Test]
    public function freezeStillAllowsResolution(): void
    {
        $this->c->set(Foo::class, fn() => new Foo());
        $this->c->freeze();

        $obj = $this->c->get(Foo::class);

        self::assertInstanceOf(Foo::class, $obj);
    }

    #[Test]
    public function isFrozenReflectsState(): void
    {
        self::assertFalse($this->c->isFrozen());

        $this->c->freeze();

        self::assertTrue($this->c->isFrozen());
    }

    #[Test]
    public function freezeBlocksAllMutations(): void
    {
        $this->c->set(Foo::class, fn() => new Foo());
        $this->c->freeze();

        $mutations = [
            fn() => $this->c->factory(Bar::class, fn() => new Bar()),
            fn() => $this->c->instance(Bar::class, new Bar()),
            fn() => $this->c->bind('x', 'y'),
            fn() => $this->c->tag('x', 'y'),
            fn() => $this->c->extend(Foo::class, fn($o) => $o),
            fn() => $this->c->remove(Foo::class),
            fn() => $this->c->inflect(Foo::class, fn() => null),
            fn() => $this->c->resolving(fn() => null),
            fn() => $this->c->afterResolving(fn() => null),
        ];

        $blocked = 0;
        foreach ($mutations as $mutation) {
            try {
                $mutation();
            } catch (ContainerException) {
                $blocked++;
            }
        }

        self::assertSame(count($mutations), $blocked, 'All mutation methods should throw when frozen');
    }

    #[Test]
    public function clearUnfreezes(): void
    {
        $this->c->set(Foo::class, fn() => new Foo());
        $this->c->freeze();
        $this->c->clear();

        self::assertFalse($this->c->isFrozen());

        // Should work now
        $this->c->set(Foo::class, fn() => new Foo());
        self::assertTrue($this->c->has(Foo::class));
    }

    #[Test]
    public function warmUpPreResolvesAllSingletons(): void
    {
        $count = 0;

        $this->c->set('a', function () use (&$count) {
            $count++;
            return new Foo();
        });

        $this->c->set('b', function () use (&$count) {
            $count++;
            return new Bar();
        });

        self::assertSame(0, $count);

        $this->c->warmUp();

        self::assertSame(2, $count);
        self::assertNotEmpty($this->c->allInstances());
    }

    #[Test]
    public function warmUpSkipsAlreadyResolved(): void
    {
        $count = 0;

        $this->c->set(Foo::class, function () use (&$count) {
            $count++;
            return new Foo();
        });

        $this->c->get(Foo::class);
        self::assertSame(1, $count);

        $this->c->warmUp();
        self::assertSame(1, $count, 'Factory should not run again if already cached');
    }

    // -------------------------------------------------------------------------
    // Definition Introspection
    // -------------------------------------------------------------------------

    #[Test]
    public function getDefinitionForSharedService(): void
    {
        $this->c->set(Foo::class, fn() => new Foo());
        $this->c->tag(Foo::class, 'controllers');

        $def = $this->c->getDefinition(Foo::class);

        self::assertNotNull($def);
        self::assertSame(Foo::class, $def['id']);
        self::assertSame('shared', $def['type']);
        self::assertContains('controllers', $def['tags']);
        self::assertFalse($def['hasExtenders']);
    }

    #[Test]
    public function getDefinitionForFactory(): void
    {
        $this->c->factory(Foo::class, fn() => new Foo());

        $def = $this->c->getDefinition(Foo::class);

        self::assertNotNull($def);
        self::assertSame('factory', $def['type']);
    }

    #[Test]
    public function getDefinitionForInstance(): void
    {
        $this->c->instance(Foo::class, new Foo());

        $def = $this->c->getDefinition(Foo::class);

        self::assertNotNull($def);
        self::assertSame('instance', $def['type']);
    }

    #[Test]
    public function getDefinitionForBinding(): void
    {
        $this->c->bind('my.foo', Foo::class);
        $this->c->set(Foo::class, fn() => new Foo());

        $def = $this->c->getDefinition('my.foo');

        self::assertNotNull($def);
        self::assertSame('my.foo', $def['id']);
        self::assertSame(Foo::class, $def['resolvedId']);
        self::assertSame(Foo::class, $def['binding']);
        self::assertSame('shared', $def['type']);
    }

    #[Test]
    public function getDefinitionReturnsNullForUnregistered(): void
    {
        $def = $this->c->getDefinition('nonexistent.service');

        self::assertNull($def);
    }

    #[Test]
    public function getDefinitionShowsExtenders(): void
    {
        $this->c->set(Foo::class, fn() => new Foo());
        $this->c->extend(Foo::class, fn($o) => $o);

        $def = $this->c->getDefinition(Foo::class);

        self::assertNotNull($def);
        self::assertTrue($def['hasExtenders']);
    }
}
