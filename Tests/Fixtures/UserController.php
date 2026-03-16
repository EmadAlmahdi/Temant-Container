<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class UserController
{
    public function __construct(public readonly LoggerInterface $logger)
    {
    }
}
