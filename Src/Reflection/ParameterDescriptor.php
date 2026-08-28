<?php

declare(strict_types=1);

namespace Temant\Container\Reflection;

use Closure;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Temant\Container\Attribute\Inject;
use UnitEnum;

use function array_filter;
use function array_values;
use function is_array;
use function is_object;
use function is_string;

/**
 * Every fact about one constructor or callable parameter that the resolver needs,
 * extracted from reflection once and then reused.
 *
 * A descriptor is pure data (with one exception, below) so it can be cached in
 * memory and, via a PSR-16 store, across processes. It records *what* the
 * parameter is, never *how* it should be resolved -- that decision depends on the
 * container's live state and is made every time in
 * {@see \Temant\Container\Resolution\ParameterResolver}.
 *
 * The one non-serialisable piece is {@see $defaultProvider}: a parameter whose
 * default value is an object created in the signature (`Foo $x = new Foo()`)
 * cannot have that value frozen, so a closure re-reads it on demand. Descriptors
 * carrying one are never written to a persistent store
 * ({@see ConstructorDescriptor::isPersistable()}).
 *
 * @internal
 */
final class ParameterDescriptor
{
    /** @var (Closure(): mixed)|null */
    private ?Closure $defaultProvider;

    /**
     * @param list<string> $unionTypeNames Non-builtin member type names, for {@see ParameterTypeKind::Union}.
     * @param (Closure(): mixed)|null $defaultProvider
     */
    public function __construct(
        public readonly string $name,
        public readonly ParameterTypeKind $typeKind,
        public readonly ?string $typeName,
        public readonly bool $isBuiltin,
        public readonly bool $allowsNull,
        public readonly bool $isVariadic,
        public readonly bool $hasDefault,
        public readonly bool $defaultIsComplex,
        public readonly mixed $defaultValue,
        public readonly ?string $injectId,
        public readonly array $unionTypeNames,
        ?Closure $defaultProvider = null,
    ) {
        $this->defaultProvider = $defaultProvider;
    }

    public static function fromReflection(ReflectionParameter $parameter): self
    {
        $type = $parameter->getType();

        $kind = match (true) {
            $type instanceof ReflectionUnionType => ParameterTypeKind::Union,
            $type instanceof ReflectionIntersectionType => ParameterTypeKind::Intersection,
            $type instanceof ReflectionNamedType => ParameterTypeKind::Named,
            default => ParameterTypeKind::None,
        };

        $typeName = null;
        $isBuiltin = false;
        $unionTypeNames = [];

        if ($type instanceof ReflectionNamedType) {
            $typeName = $type->getName();
            $isBuiltin = $type->isBuiltin();
        } elseif ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $member) {
                if ($member instanceof ReflectionNamedType && !$member->isBuiltin()) {
                    $unionTypeNames[] = $member->getName();
                }
            }
        }

        $hasDefault = $parameter->isDefaultValueAvailable();
        $defaultIsComplex = false;
        $defaultValue = null;
        $defaultProvider = null;

        if ($hasDefault) {
            $value = $parameter->getDefaultValue();

            if (is_object($value) && !$value instanceof UnitEnum) {
                $defaultIsComplex = true;
                $defaultProvider = static fn(): mixed => $parameter->getDefaultValue();
            } else {
                $defaultValue = $value;
            }
        }

        $inject = $parameter->getAttributes(Inject::class);

        return new self(
            name: $parameter->getName(),
            typeKind: $kind,
            typeName: $typeName,
            isBuiltin: $isBuiltin,
            allowsNull: $type?->allowsNull() ?? true,
            isVariadic: $parameter->isVariadic(),
            hasDefault: $hasDefault,
            defaultIsComplex: $defaultIsComplex,
            defaultValue: $defaultValue,
            injectId: $inject === [] ? null : $inject[0]->newInstance()->id,
            unionTypeNames: $unionTypeNames,
            defaultProvider: $defaultProvider,
        );
    }

    /**
     * The parameter's default value. Only call when {@see $hasDefault} is true.
     */
    public function default(): mixed
    {
        if (!$this->defaultIsComplex) {
            return $this->defaultValue;
        }

        $provider = $this->defaultProvider;

        return $provider !== null ? $provider() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'name' => $this->name,
            'typeKind' => $this->typeKind,
            'typeName' => $this->typeName,
            'isBuiltin' => $this->isBuiltin,
            'allowsNull' => $this->allowsNull,
            'isVariadic' => $this->isVariadic,
            'hasDefault' => $this->hasDefault,
            'defaultIsComplex' => $this->defaultIsComplex,
            'defaultValue' => $this->defaultValue,
            'injectId' => $this->injectId,
            'unionTypeNames' => $this->unionTypeNames,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $name = $data['name'] ?? '';
        $typeKind = $data['typeKind'] ?? ParameterTypeKind::None;
        $typeName = $data['typeName'] ?? null;
        $injectId = $data['injectId'] ?? null;
        $unionTypeNames = $data['unionTypeNames'] ?? [];

        $this->name = is_string($name) ? $name : '';
        $this->typeKind = $typeKind instanceof ParameterTypeKind ? $typeKind : ParameterTypeKind::None;
        $this->typeName = is_string($typeName) ? $typeName : null;
        $this->isBuiltin = ($data['isBuiltin'] ?? false) === true;
        $this->allowsNull = ($data['allowsNull'] ?? false) === true;
        $this->isVariadic = ($data['isVariadic'] ?? false) === true;
        $this->hasDefault = ($data['hasDefault'] ?? false) === true;
        $this->defaultIsComplex = ($data['defaultIsComplex'] ?? false) === true;
        $this->defaultValue = $data['defaultValue'] ?? null;
        $this->injectId = is_string($injectId) ? $injectId : null;
        $this->unionTypeNames = array_values(array_filter(
            is_array($unionTypeNames) ? $unionTypeNames : [],
            is_string(...),
        ));
        $this->defaultProvider = null;
    }
}
