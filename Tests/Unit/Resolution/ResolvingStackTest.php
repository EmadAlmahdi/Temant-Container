<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Unit\Resolution;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Resolution\ResolvingStack;

final class ResolvingStackTest extends TestCase
{
    #[Test]
    public function tracksDepthMembershipAndCurrent(): void
    {
        $stack = new ResolvingStack();

        self::assertTrue($stack->isEmpty());
        self::assertNull($stack->current());

        $stack->push('A');
        $stack->push('B');

        self::assertFalse($stack->isEmpty());
        self::assertSame('B', $stack->current());
        self::assertTrue($stack->contains('A'));
        self::assertFalse($stack->contains('C'));

        $stack->pop();

        self::assertSame('A', $stack->current());
        self::assertFalse($stack->contains('B'));
    }

    #[Test]
    public function chainRendersTheDependencyPath(): void
    {
        $stack = new ResolvingStack();
        $stack->push('A');
        $stack->push('B');

        self::assertSame('A -> B -> A', $stack->chain('A'));
    }
}
