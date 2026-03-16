<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

interface LoggerAwareInterface
{
    public function setLogger(LoggerInterface $logger): void;

    public function getLogger(): ?LoggerInterface;
}
