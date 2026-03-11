<?php

declare(strict_types=1);

namespace Temant\Container\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;

/**
 * Base exception for all container errors.
 *
 * Implements PSR-11's ContainerExceptionInterface so that all container exceptions
 * (including subclasses like ClassResolutionException and UnresolvableParameterException)
 * are catchable via the PSR-11 contract.
 */
class ContainerException extends Exception implements ContainerExceptionInterface
{
}
