<?php

declare(strict_types=1);

namespace Tests\Temant\Container;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Tests\Temant\Container\Fixtures\AdminController;
use Tests\Temant\Container\Fixtures\ConsoleLogger;
use Tests\Temant\Container\Fixtures\FileLogger;
use Tests\Temant\Container\Fixtures\LoggerInterface;
use Tests\Temant\Container\Fixtures\UserController;

final class ContextualBindingTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function contextualBindingResolvesCorrectImplementation(): void
    {
        $this->c->set(FileLogger::class, fn() => new FileLogger());
        $this->c->set(ConsoleLogger::class, fn() => new ConsoleLogger());

        $this->c->when(UserController::class)
                ->needs(LoggerInterface::class)
                ->give(FileLogger::class);

        $this->c->when(AdminController::class)
                ->needs(LoggerInterface::class)
                ->give(ConsoleLogger::class);

        /** @var UserController $userCtrl */
        $userCtrl = $this->c->get(UserController::class);
        /** @var AdminController $adminCtrl */
        $adminCtrl = $this->c->get(AdminController::class);

        self::assertInstanceOf(FileLogger::class, $userCtrl->logger);
        self::assertInstanceOf(ConsoleLogger::class, $adminCtrl->logger);
    }

    #[Test]
    public function contextualBindingWithClosureFactory(): void
    {
        $this->c->when(UserController::class)
                ->needs(LoggerInterface::class)
                ->give(fn() => new FileLogger());

        /** @var UserController $ctrl */
        $ctrl = $this->c->get(UserController::class);

        self::assertInstanceOf(FileLogger::class, $ctrl->logger);
    }

    #[Test]
    public function contextualBindingFallsBackToGlobalBinding(): void
    {
        $this->c->bind(LoggerInterface::class, FileLogger::class);

        // No contextual binding for AdminController — should use global binding
        /** @var AdminController $ctrl */
        $ctrl = $this->c->get(AdminController::class);

        self::assertInstanceOf(FileLogger::class, $ctrl->logger);
    }

    #[Test]
    public function contextualBindingOverridesGlobalBinding(): void
    {
        $this->c->bind(LoggerInterface::class, FileLogger::class);

        $this->c->when(UserController::class)
                ->needs(LoggerInterface::class)
                ->give(ConsoleLogger::class);

        /** @var UserController $ctrl */
        $ctrl = $this->c->get(UserController::class);

        self::assertInstanceOf(ConsoleLogger::class, $ctrl->logger);
    }
}
