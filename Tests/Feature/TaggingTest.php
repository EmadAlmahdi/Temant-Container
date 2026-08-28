<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\Foo;

final class TaggingTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function taggedResolvesEveryMemberInOrder(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->factory(Bar::class, fn(): Bar => new Bar());
        $this->c->tag(Foo::class, 'group');
        $this->c->tag(Bar::class, 'group');

        $items = $this->c->tagged('group');

        self::assertInstanceOf(Foo::class, $items[0]);
        self::assertInstanceOf(Bar::class, $items[1]);
    }

    #[Test]
    public function repeatedTaggingOfTheSameIdIsIgnored(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());
        $this->c->tag(Foo::class, 'group');
        $this->c->tag(Foo::class, 'group');

        self::assertCount(1, $this->c->tagged('group'));
    }

    #[Test]
    public function unknownTagResolvesToAnEmptyList(): void
    {
        self::assertSame([], $this->c->tagged('nope'));
    }
}
