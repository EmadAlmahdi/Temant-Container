<?php

declare(strict_types=1);

namespace Temant\Container\Reflection;

/**
 * The shape of a parameter's type declaration, as seen by the resolver.
 *
 * @internal
 */
enum ParameterTypeKind: string
{
    /** No type declaration. */
    case None = 'none';

    /** A single named type (`Foo`, `?Foo`, `string`, ...). */
    case Named = 'named';

    /** A union type (`Foo|Bar`). */
    case Union = 'union';

    /** An intersection type (`Foo&Bar`) -- not resolvable. */
    case Intersection = 'intersection';
}
