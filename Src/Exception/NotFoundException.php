<?php

declare(strict_types=1);

namespace Temant\Container\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Exception thrown when a requested entry is not found in the container.
 *
 * Implements PSR-11's NotFoundExceptionInterface for interoperability.
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    /**
     * Creates an exception for a missing entry in the container.
     *
     * @param string $id The identifier of the missing entry.
     * @return self
     */
    public static function forEntry(string $id): self
    {
        return new self("No entry found in the container for identifier: {$id}");
    }
}
