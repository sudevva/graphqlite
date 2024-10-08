<?php

declare(strict_types=1);

namespace TheCodingMachine\GraphQLite\Mappers;

use ReflectionClass;

use function array_keys;

/**
 * The cached results of a GlobTypeMapper
 */
class GlobTypeMapperCache
{
    /** @var array<class-string<object>,class-string<object>> Maps a domain class to the GraphQL type annotated class */
    private array $mapClassToTypeArray = [];
    /** @var array<string,class-string<object>> Maps a GraphQL type name to the GraphQL type annotated class */
    private array $mapNameToType = [];
    /** @var array<class-string<object>,array{0: class-string<object>, 1: string}> Maps a domain class to the factory method that creates the input type in the form [classname, methodName] */
    private array $mapClassToFactory = [];
    /** @var array<string,array<int,string>> Maps a GraphQL input type name to the factory method that creates the input type in the form [classname, methodName] */
    private array $mapInputNameToFactory = [];
    /** @var array<string,array<int, array{0: class-string<object>, 1: string}>> Maps a GraphQL type name to one or many decorators (with the @Decorator annotation) */
    private array $mapInputNameToDecorator = [];
    /** @var array<class-string<object>,array{0: class-string<object>, 1: string, 2: string|null, 3: bool}> Maps a domain class to the input */
    private array $mapClassToInput = [];
    /** @var array<string,array{0: class-string<object>, 1: string|null, 2: bool}> Maps a GraphQL type name to the input */
    private array $mapNameToInput = [];

    /**
     * Merges annotations of a given class in the global cache.
     *
     * @param ReflectionClass<object>|class-string $sourceClass
     */
    public function registerAnnotations(ReflectionClass|string $sourceClass, GlobAnnotationsCache $globAnnotationsCache): void
    {
        $className = $sourceClass instanceof ReflectionClass ? $sourceClass->getName() : $sourceClass;

        $typeClassName = $globAnnotationsCache->getTypeClassName();
        if ($typeClassName !== null) {
            if (isset($this->mapClassToTypeArray[$typeClassName]) && $globAnnotationsCache->isDefault()) {
                throw DuplicateMappingException::createForType($typeClassName, $this->mapClassToTypeArray[$typeClassName], $className);
            }

            if ($globAnnotationsCache->isDefault()) {
                $objectClassName = $typeClassName;
                $this->mapClassToTypeArray[$objectClassName] = $className;
            }

            $typeName = $globAnnotationsCache->getTypeName();
            $this->mapNameToType[$typeName] = $className;
        }

        foreach ($globAnnotationsCache->getFactories() as $methodName => [$inputName, $inputClassName, $isDefault, $declaringClass]) {
            if ($isDefault) {
                if ($inputClassName !== null && isset($this->mapClassToFactory[$inputClassName])) {
                    throw DuplicateMappingException::createForFactory($inputClassName, $this->mapClassToFactory[$inputClassName][0], $this->mapClassToFactory[$inputClassName][1], $className, $methodName);
                }
            } else {
                // If this is not the default factory, let's not map the class name to the factory.
                $inputClassName = null;
            }

            $refArray = [$declaringClass, $methodName];
            if ($inputClassName !== null) {
                $this->mapClassToFactory[$inputClassName] = $refArray;
            }
            $this->mapInputNameToFactory[$inputName] = $refArray;
        }

        foreach ($globAnnotationsCache->getInputs() as $inputName => [$inputClassName, $isDefault, $description, $isUpdate]) {
            if ($isDefault) {
                if (isset($this->mapClassToInput[$inputClassName])) {
                    throw DuplicateMappingException::createForDefaultInput($className);
                }

                $this->mapClassToInput[$inputClassName] = [$className, $inputName, $description, $isUpdate];
            }

            if (isset($this->mapNameToInput[$inputName])) {
                throw DuplicateMappingException::createForTwoInputs($inputName, $this->mapNameToInput[$inputName][0], $inputClassName);
            }

            $this->mapNameToInput[$inputName] = [$inputClassName, $description, $isUpdate];
        }

        foreach ($globAnnotationsCache->getDecorators() as $methodName => [$inputName, $declaringClass]) {
            $this->mapInputNameToDecorator[$inputName][] = [$declaringClass, $methodName];
        }
    }

    /**
     * @param class-string<object> $className
     *
     * @return class-string<object>|null
     */
    public function getTypeByObjectClass(string $className): string|null
    {
        return $this->mapClassToTypeArray[$className] ?? null;
    }

    /** @return class-string<object>[] */
    public function getSupportedClasses(): array
    {
        return array_keys($this->mapClassToTypeArray);
    }

    /** @return class-string<object>|null */
    public function getTypeByGraphQLTypeName(string $graphqlTypeName): string|null
    {
        return $this->mapNameToType[$graphqlTypeName] ?? null;
    }

    /** @return array<int,string>|null Maps a GraphQL input type name to the factory method that creates the input type in the form [classname, methodname] */
    public function getFactoryByGraphQLInputTypeName(string $graphqlTypeName): array|null
    {
        return $this->mapInputNameToFactory[$graphqlTypeName] ?? null;
    }

    /** @return array<int, string[]>|null A pointer to the decorators methods [$className, $methodName] or null on cache miss */
    public function getDecorateByGraphQLInputTypeName(string $graphqlTypeName): array|null
    {
        return $this->mapInputNameToDecorator[$graphqlTypeName] ?? null;
    }

    /** @return string[]|null A pointer to the factory [$className, $methodName] or null on cache miss */
    public function getFactoryByObjectClass(string $className): array|null
    {
        return $this->mapClassToFactory[$className] ?? null;
    }

    /** @return array{0: class-string<object>, 1: string|null, 2: bool}|null */
    public function getInputByGraphQLInputTypeName(string $graphqlTypeName): array|null
    {
        return $this->mapNameToInput[$graphqlTypeName] ?? null;
    }

    /** @return array{0: class-string<object>, 1: string, 2: string|null, 3: bool}|null */
    public function getInputByObjectClass(string $className): array|null
    {
        return $this->mapClassToInput[$className] ?? null;
    }
}
