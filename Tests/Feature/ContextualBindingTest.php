<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\ContainerException;
use Tests\Temant\Container\Fixtures\AdminController;
use Tests\Temant\Container\Fixtures\ChartWidget;
use Tests\Temant\Container\Fixtures\ConsoleLogger;
use Tests\Temant\Container\Fixtures\Dashboard;
use Tests\Temant\Container\Fixtures\FileLogger;
use Tests\Temant\Container\Fixtures\LoggerInterface;
use Tests\Temant\Container\Fixtures\TableWidget;
use Tests\Temant\Container\Fixtures\UserController;
use Tests\Temant\Container\Fixtures\WidgetInterface;

final class ContextualBindingTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function differentConsumersGetDifferentImplementations(): void
    {
        $this->c->when(UserController::class)->needs(LoggerInterface::class)->give(FileLogger::class);
        $this->c->when(AdminController::class)->needs(LoggerInterface::class)->give(ConsoleLogger::class);

        self::assertInstanceOf(FileLogger::class, $this->c->get(UserController::class)->logger);
        self::assertInstanceOf(ConsoleLogger::class, $this->c->get(AdminController::class)->logger);
    }

    #[Test]
    public function acceptsAClosureFactory(): void
    {
        $this->c->when(UserController::class)
            ->needs(LoggerInterface::class)
            ->give(fn(): FileLogger => new FileLogger());

        self::assertInstanceOf(FileLogger::class, $this->c->get(UserController::class)->logger);
    }

    #[Test]
    public function contextualBindingOverridesTheGlobalBinding(): void
    {
        $this->c->bind(LoggerInterface::class, FileLogger::class);
        $this->c->when(UserController::class)->needs(LoggerInterface::class)->give(ConsoleLogger::class);

        self::assertInstanceOf(ConsoleLogger::class, $this->c->get(UserController::class)->logger);
        self::assertInstanceOf(FileLogger::class, $this->c->get(AdminController::class)->logger);
    }

    #[Test]
    public function giveListSatisfiesATypedVariadic(): void
    {
        $this->c->when(Dashboard::class)
            ->needs(WidgetInterface::class)
            ->give([ChartWidget::class, TableWidget::class]);

        $widgets = $this->c->get(Dashboard::class)->widgets;

        self::assertCount(2, $widgets);
        self::assertInstanceOf(ChartWidget::class, $widgets[0]);
        self::assertInstanceOf(TableWidget::class, $widgets[1]);
    }

    #[Test]
    public function giveTaggedSatisfiesATypedVariadic(): void
    {
        $this->c->set(ChartWidget::class, fn(): ChartWidget => new ChartWidget());
        $this->c->set(TableWidget::class, fn(): TableWidget => new TableWidget());
        $this->c->tag(ChartWidget::class, 'widgets');
        $this->c->tag(TableWidget::class, 'widgets');

        $this->c->when(Dashboard::class)->needs(WidgetInterface::class)->giveTagged('widgets');

        self::assertCount(2, $this->c->get(Dashboard::class)->widgets);
    }

    #[Test]
    public function giveBeforeNeedsIsRejected(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/needs\(\) before give\(\)/');

        $this->c->when(UserController::class)->give(FileLogger::class);
    }
}
