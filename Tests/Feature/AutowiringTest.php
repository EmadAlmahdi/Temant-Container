<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\NotFoundException;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\ConsoleLogger;
use Tests\Temant\Container\Fixtures\FileLogger;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\InjectAggregate;
use Tests\Temant\Container\Fixtures\InjectService;
use Tests\Temant\Container\Fixtures\LoggerInterface;
use Tests\Temant\Container\Fixtures\NullableUnionService;
use Tests\Temant\Container\Fixtures\RequiredUnionService;
use Tests\Temant\Container\Fixtures\UnionService;
use Temant\Container\Exception\UnresolvableParameterException;

final class AutowiringTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function resolvesUnregisteredClassesAndCachesByDefault(): void
    {
        $a = $this->c->get(Foo::class);

        self::assertInstanceOf(Foo::class, $a);
        self::assertSame($a, $this->c->get(Foo::class));
    }

    #[Test]
    public function cachingCanBeDisabled(): void
    {
        $c = new Container(autowiringEnabled: true, cacheAutowire: false);

        self::assertNotSame($c->get(Foo::class), $c->get(Foo::class));
    }

    #[Test]
    public function disablingAutowiringMakesUnregisteredClassesUnavailable(): void
    {
        $this->c->setAutowiring(false);

        self::assertFalse($this->c->hasAutowiring());
        self::assertFalse($this->c->has(Foo::class));

        $this->expectException(NotFoundException::class);
        $this->c->get(Foo::class);
    }

    #[Test]
    public function hasIsFalseForNonInstantiableClasses(): void
    {
        self::assertFalse($this->c->has(LoggerInterface::class));
    }

    #[Test]
    public function resolvesUnionTypesByTryingEachMember(): void
    {
        $this->c->set(Bar::class, fn(): Bar => new Bar());

        /** @var UnionService $service */
        $service = $this->c->get(UnionService::class);

        self::assertInstanceOf(Bar::class, $service->dependency);
    }

    #[Test]
    public function autowiresTheFirstBuildableUnionMemberWhenNoneAreBound(): void
    {
        /** @var UnionService $service */
        $service = $this->c->get(UnionService::class);

        self::assertInstanceOf(Foo::class, $service->dependency);
    }

    #[Test]
    public function fallsBackToNullForAnUnresolvableNullableUnion(): void
    {
        /** @var NullableUnionService $service */
        $service = $this->c->get(NullableUnionService::class);

        self::assertNull($service->dependency);
    }

    #[Test]
    public function throwsWhenNoUnionMemberCanBeResolvedAndNoneIsNullable(): void
    {
        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessageMatches('/Union types/');

        $this->c->get(RequiredUnionService::class);
    }

    #[Test]
    public function contextualBindingResolvesAUnionParameter(): void
    {
        $this->c->when(UnionService::class)
            ->needs(Foo::class)
            ->give(fn(): Foo => new Foo());

        /** @var UnionService $service */
        $service = $this->c->get(UnionService::class);

        self::assertInstanceOf(Foo::class, $service->dependency);
    }

    #[Test]
    public function injectAttributePicksTheNamedEntry(): void
    {
        $this->c->set(ConsoleLogger::class, fn(): ConsoleLogger => new ConsoleLogger());
        $this->c->set(FileLogger::class, fn(): FileLogger => new FileLogger());

        /** @var InjectService $service */
        $service = $this->c->get(InjectService::class);

        self::assertInstanceOf(ConsoleLogger::class, $service->primary);
        self::assertInstanceOf(FileLogger::class, $service->fallback);
    }

    #[Test]
    public function injectAttributeOnAVariadicPullsFromATag(): void
    {
        $this->c->set(ConsoleLogger::class, fn(): ConsoleLogger => new ConsoleLogger());
        $this->c->set(FileLogger::class, fn(): FileLogger => new FileLogger());
        $this->c->tag(ConsoleLogger::class, 'loggers');
        $this->c->tag(FileLogger::class, 'loggers');

        /** @var InjectAggregate $service */
        $service = $this->c->get(InjectAggregate::class);

        self::assertCount(2, $service->loggers);
    }
}
