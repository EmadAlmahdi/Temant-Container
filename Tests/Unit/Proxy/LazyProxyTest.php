<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Unit\Proxy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Temant\Container\Proxy\LazyProxy;

final class LazyProxyTest extends TestCase
{
    #[Test]
    public function defersUntilFirstUse(): void
    {
        $created = false;

        $proxy = new LazyProxy(function () use (&$created): object {
            $created = true;

            return new stdClass();
        });

        self::assertFalse($created);
        self::assertFalse($proxy->isInitialized());

        $proxy->getTarget();

        self::assertTrue($created);
        self::assertTrue($proxy->isInitialized());
    }

    #[Test]
    public function delegatesMethodCalls(): void
    {
        $inner = new class {
            public function greet(string $name): string
            {
                return "hi {$name}";
            }
        };
        $proxy = new LazyProxy(fn(): object => $inner);

        self::assertSame('hi sam', $proxy->greet('sam'));
    }

    #[Test]
    public function delegatesMethodsAndProperties(): void
    {
        $inner = new stdClass();
        $inner->value = 42;
        $proxy = new LazyProxy(fn(): object => $inner);

        self::assertSame(42, $proxy->value);
        $proxy->name = 'set';
        self::assertSame('set', $inner->name);
        self::assertTrue(isset($proxy->name));
        unset($proxy->name);
        self::assertFalse(isset($inner->name));
    }

    #[Test]
    public function buildsTheRealInstanceOnlyOnce(): void
    {
        $count = 0;
        $proxy = new LazyProxy(function () use (&$count): object {
            $count++;

            return new stdClass();
        });

        $proxy->getTarget();
        $proxy->getTarget();

        self::assertSame(1, $count);
    }

    #[Test]
    public function castsToStringViaTheTarget(): void
    {
        $inner = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringified';
            }
        };

        $proxy = new LazyProxy(fn(): object => $inner);

        self::assertSame('stringified', (string) $proxy);
    }
}
