<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Tests\Temant\Container\Fixtures\CallTarget;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\SomeClass;

final class CallableInvocationTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function resolvesClosureParameters(): void
    {
        self::assertSame(Foo::class, $this->c->call(fn(Foo $foo): string => $foo::class));
    }

    #[Test]
    public function appliesNamedOverridesToArrayCallables(): void
    {
        $result = $this->c->call([new CallTarget(), 'method'], ['name' => 'override']);

        self::assertSame(SomeClass::class . ':override', $result);
    }

    #[Test]
    public function supportsClassAtMethodSyntax(): void
    {
        $result = $this->c->call(CallTarget::class . '@method', ['name' => 'x']);

        self::assertSame(SomeClass::class . ':x', $result);
    }

    #[Test]
    public function classAtMethodResolvesTheInstanceFromTheContainer(): void
    {
        $target = new CallTarget();
        $this->c->instance(CallTarget::class, $target);

        self::assertSame(SomeClass::class . ':y', $this->c->call(CallTarget::class . '@method', ['name' => 'y']));
    }
}
