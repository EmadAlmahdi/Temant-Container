<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Unit\Resolution;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Temant\Container\Container;
use Temant\Container\Resolution\ResolutionPipeline;
use Tests\Temant\Container\Fixtures\LoggerAwareInterface;
use Tests\Temant\Container\Fixtures\LoggerAwareService;

final class ResolutionPipelineTest extends TestCase
{
    private ResolutionPipeline $pipeline;
    private Container $container;

    protected function setUp(): void
    {
        $this->pipeline = new ResolutionPipeline();
        $this->container = new Container();
    }

    #[Test]
    public function runsHooksInDocumentedOrder(): void
    {
        $order = [];

        $this->pipeline->extend('id', function (object $o) use (&$order): object {
            $order[] = 'extend';

            return $o;
        });
        $this->pipeline->inflect(stdClass::class, function () use (&$order): void {
            $order[] = 'inflect';
        });
        $this->pipeline->resolving('id', function () use (&$order): void {
            $order[] = 'resolving';
        });
        $this->pipeline->resolving(function () use (&$order): void {
            $order[] = 'global-resolving';
        });
        $this->pipeline->afterResolving('id', function () use (&$order): void {
            $order[] = 'after';
        });

        $this->pipeline->process('id', new stdClass(), $this->container);

        self::assertSame(['extend', 'inflect', 'resolving', 'global-resolving', 'after'], $order);
    }

    #[Test]
    public function extendersCanReplaceTheInstance(): void
    {
        $replacement = new stdClass();
        $this->pipeline->extend('id', fn(): object => $replacement);

        self::assertSame($replacement, $this->pipeline->process('id', new stdClass(), $this->container));
    }

    #[Test]
    public function inflectorsMatchByInstanceofAndMutateInPlace(): void
    {
        $this->container->set('logger', fn() => new \Tests\Temant\Container\Fixtures\FileLogger());

        $this->pipeline->inflect(LoggerAwareInterface::class, function (object $service, Container $c): void {
            /** @var LoggerAwareService $service */
            $service->setLogger($c->get('logger'));
        });

        $service = new LoggerAwareService();
        $processed = $this->pipeline->process('id', $service, $this->container);

        self::assertSame($service, $processed);
        self::assertNotNull($service->getLogger());
    }

    #[Test]
    public function idCallbackWithoutAClosureIsANoOp(): void
    {
        $this->pipeline->resolving('id');
        $this->pipeline->afterResolving('id');

        $instance = new stdClass();

        self::assertSame($instance, $this->pipeline->process('id', $instance, $this->container));
    }

    #[Test]
    public function forgetDropsExtendersForAnId(): void
    {
        $this->pipeline->extend('id', fn(object $o): object => $o);
        self::assertTrue($this->pipeline->hasExtenders('id'));

        $this->pipeline->forget('id');

        self::assertFalse($this->pipeline->hasExtenders('id'));
    }
}
