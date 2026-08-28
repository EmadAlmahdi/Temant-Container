<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Attribute\Inject;
use Temant\Container\Container;
use Tests\Temant\Container\Fixtures\ChartWidget;
use Tests\Temant\Container\Fixtures\ConsoleLogger;
use Tests\Temant\Container\Fixtures\ConstructorWithTypedVariadic;
use Tests\Temant\Container\Fixtures\Dashboard;
use Tests\Temant\Container\Fixtures\FileLogger;
use Tests\Temant\Container\Fixtures\LoggerInterface;
use Tests\Temant\Container\Fixtures\WidgetInterface;

final class VariadicResolutionTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function typedVariadicIsFilledFromTaggedServices(): void
    {
        $this->c->set(FileLogger::class, fn(): FileLogger => new FileLogger());
        $this->c->set(ConsoleLogger::class, fn(): ConsoleLogger => new ConsoleLogger());
        $this->c->tag(FileLogger::class, LoggerInterface::class);
        $this->c->tag(ConsoleLogger::class, LoggerInterface::class);

        /** @var ConstructorWithTypedVariadic $obj */
        $obj = $this->c->get(ConstructorWithTypedVariadic::class);

        self::assertCount(2, $obj->loggers);
        self::assertInstanceOf(FileLogger::class, $obj->loggers[0]);
        self::assertInstanceOf(ConsoleLogger::class, $obj->loggers[1]);
    }

    #[Test]
    public function typedVariadicFallsBackToASingleRegisteredInstance(): void
    {
        $this->c->set(LoggerInterface::class, fn(): FileLogger => new FileLogger());

        /** @var ConstructorWithTypedVariadic $obj */
        $obj = $this->c->get(ConstructorWithTypedVariadic::class);

        self::assertCount(1, $obj->loggers);
        self::assertInstanceOf(FileLogger::class, $obj->loggers[0]);
    }

    #[Test]
    public function typedVariadicResolvesToEmptyWhenNothingMatches(): void
    {
        // Autowiring is on so the outer class is built by reflection, but nothing
        // satisfies LoggerInterface -- a variadic legitimately accepts zero arguments.
        /** @var ConstructorWithTypedVariadic $obj */
        $obj = $this->c->get(ConstructorWithTypedVariadic::class);

        self::assertCount(0, $obj->loggers);
    }

    #[Test]
    public function injectAttributeOnAVariadicFallsBackToASingleEntry(): void
    {
        $this->c->set(FileLogger::class, fn(): FileLogger => new FileLogger());

        $count = $this->c->call(
            fn(#[Inject(FileLogger::class)] LoggerInterface ...$loggers): int => count($loggers),
        );

        self::assertSame(1, $count);
    }

    #[Test]
    public function builtinTypedVariadicResolvesToEmpty(): void
    {
        $sum = $this->c->call(fn(int ...$numbers): int => array_sum($numbers));

        self::assertSame(0, $sum);
    }

    #[Test]
    public function contextualBindingCanGiveASingleIdToAVariadic(): void
    {
        $this->c->set(ChartWidget::class, fn(): ChartWidget => new ChartWidget());
        $this->c->when(Dashboard::class)
            ->needs(WidgetInterface::class)
            ->give(ChartWidget::class);

        $widgets = $this->c->get(Dashboard::class)->widgets;

        self::assertCount(1, $widgets);
        self::assertInstanceOf(ChartWidget::class, $widgets[0]);
    }

    #[Test]
    public function makeAppliesAnArrayOverrideToAVariadicConstructorParameter(): void
    {
        /** @var ConstructorWithTypedVariadic $obj */
        $obj = $this->c->make(ConstructorWithTypedVariadic::class, [
            'loggers' => [new FileLogger(), new ConsoleLogger()],
        ]);

        self::assertCount(2, $obj->loggers);
    }

    #[Test]
    public function callInvokesAClosureWithAVariadicParameter(): void
    {
        $this->c->set(FileLogger::class, fn(): FileLogger => new FileLogger());
        $this->c->tag(FileLogger::class, LoggerInterface::class);

        $count = $this->c->call(fn(LoggerInterface ...$loggers): int => count($loggers));

        self::assertSame(1, $count);
    }
}
