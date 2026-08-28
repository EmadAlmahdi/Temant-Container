<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class Dashboard
{
    /** @var list<WidgetInterface> */
    public readonly array $widgets;

    public function __construct(WidgetInterface ...$widgets)
    {
        $this->widgets = $widgets;
    }
}
