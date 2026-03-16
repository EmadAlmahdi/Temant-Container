<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class AdminController
{
    public function __construct(public readonly LoggerInterface $logger)
    {
    }
}
