<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

class HeavyService
{
    public function __construct(public readonly string $marker = 'heavy')
    {
    }

    public function work(): string
    {
        return 'working:' . $this->marker;
    }
}
