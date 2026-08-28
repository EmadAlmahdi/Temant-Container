<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class RequiredUnionService
{
    public function __construct(public readonly LoggerInterface|WidgetInterface $dependency)
    {
    }
}
