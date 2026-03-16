<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class LoggerAwareService implements LoggerAwareInterface
{
    private ?LoggerInterface $logger = null;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }
}
