<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Temant\Container\Container;
use Temant\Container\ContainerInterface;
use Tests\Temant\Container\Fixtures\Foo;

final class DecorationTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function extendWrapsASharedService(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        $wrapper = new class extends Foo {
            public Foo $inner;
        };

        $this->c->extend(Foo::class, function (object $service) use ($wrapper): object {
            $wrapper->inner = $service;

            return $wrapper;
        });

        self::assertSame($wrapper, $this->c->get(Foo::class));
    }

    #[Test]
    public function multipleExtendersApplyInRegistrationOrder(): void
    {
        $this->c->set('counter', fn(): stdClass => (object) ['value' => 1]);
        $this->c->extend('counter', function (object $s): object {
            $s->value += 4;

            return $s;
        });
        $this->c->extend('counter', function (object $s): object {
            $s->value *= 3;

            return $s;
        });

        self::assertSame(15, $this->c->get('counter')->value);
    }

    #[Test]
    public function extendersRunEachTimeAFactoryResolves(): void
    {
        $calls = 0;
        $this->c->factory(Foo::class, fn(): Foo => new Foo());
        $this->c->extend(Foo::class, function (object $s) use (&$calls): object {
            $calls++;

            return $s;
        });

        $this->c->get(Foo::class);
        $this->c->get(Foo::class);

        self::assertSame(2, $calls);
    }

    #[Test]
    public function extendMayBeRegisteredBeforeTheServiceItself(): void
    {
        $this->c->extend(Foo::class, fn(object $s, ContainerInterface $c): object => new class ($s) extends Foo {
            public function __construct(public Foo $inner)
            {
            }
        });

        $this->c->set(Foo::class, fn(): Foo => new Foo());

        $resolved = $this->c->get(Foo::class);

        self::assertInstanceOf(Foo::class, $resolved);
        self::assertObjectHasProperty('inner', $resolved);
    }
}
